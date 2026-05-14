<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    GlobalPayments
 * @copyright Since 2021 GlobalPayments
 * @license   LICENSE
 */

namespace GlobalPayments\PaymentGatewayProvider\Security;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 3DS Security Helper - Prevents carding attacks on 3DS enrollment endpoints
 *
 * Security measures:
 * 1. Cryptographically signed tokens (HMAC-SHA256)
 * 2. Token bound to client IP address
 * 3. Token expiration (5 minutes)
 * 4. Token usage limits (max 2 uses per token)
 * 5. Rate limiting (2 requests per minute per IP)
 * 6. Hourly limits (10 requests per hour per IP)
 *
 * Uses file-based caching instead of database table for rate limiting.
 */
class ThreeDSecureSecurity
{
    /**
     * Token expiration time in seconds (5 minutes)
     */
    private const TOKEN_EXPIRY_SECONDS = 300;

    /**
     * Maximum uses per token
     */
    private const MAX_TOKEN_USES = 2;

    /**
     * Rate limit: requests per minute
     */
    private const RATE_LIMIT_PER_MINUTE = 2;

    /**
     * Hourly limit: requests per hour
     */
    private const HOURLY_LIMIT = 10;

    /**
     * Cache file prefix
     */
    private const CACHE_PREFIX_TOKEN = 'gp_3ds_token_';

    /**
     * Cache file prefix for rate limiting
     */
    private const CACHE_PREFIX_RATE = 'gp_3ds_rate_';

    /**
     * Cache file prefix for hourly limiting
     */
    private const CACHE_PREFIX_HOURLY = 'gp_3ds_hourly_';

    /**
     * Lock timeout in seconds for atomic counter operations
     */
    private const LOCK_TIMEOUT_SECONDS = 5;

    /**
     * Cache directory path
     *
     * @var string
     */
    private $cacheDir;

    /**
     * Translator instance
     *
     * @var \Symfony\Component\Translation\TranslatorInterface|null
     */
    private $translator;

    /**
     * @param \Symfony\Component\Translation\TranslatorInterface|null $translator
     */
    public function __construct($translator = null)
    {
        $this->translator = $translator;
        $this->cacheDir = $this->getCacheDirectory();
        $this->ensureCacheDirectoryExists();
    }

    /**
     * Generate a cryptographically signed security token bound to the client IP.
     *
     * Token format: timestamp:ip_hash:signature
     *
     * @return string
     */
    public function generateSecurityToken(): string
    {
        $timestamp = time();
        $clientIp = $this->getClientIp();
        $ipHash = substr(md5($clientIp . $this->getSecretKey()), 0, 16);

        $data = 'gp3ds_' . $timestamp . '_' . $ipHash;
        $signature = hash_hmac('sha256', $data, $this->getAuthKey());

        return $timestamp . ':' . $ipHash . ':' . $signature;
    }

    /**
     * Validate the security token from the request.
     *
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateSecurityToken(): array
    {
        $token = \Tools::getValue('gp3ds_token');

        if (empty($token)) {
            return [
                'valid' => false,
                'error' => $this->trans('Security token missing. Please refresh the page and try again.')
            ];
        }

        // Parse token format: timestamp:ip_hash:signature
        $parts = explode(':', $token);
        if (count($parts) !== 3) {
            return [
                'valid' => false,
                'error' => $this->trans('Invalid security token. Please refresh the page and try again.')
            ];
        }

        $timestamp = (int) $parts[0];
        $tokenIpHash = $parts[1];
        $providedSignature = $parts[2];

        // Verify the request comes from the same IP that generated the token
        $clientIp = $this->getClientIp();
        $currentIpHash = substr(md5($clientIp . $this->getSecretKey()), 0, 16);

        if (!hash_equals($tokenIpHash, $currentIpHash)) {
            return [
                'valid' => false,
                'error' => $this->trans('Security verification failed. Please refresh the page and try again.')
            ];
        }

        // Verify the HMAC signature (proves token wasn't forged)
        $expectedData = 'gp3ds_' . $timestamp . '_' . $tokenIpHash;
        $expectedSignature = hash_hmac('sha256', $expectedData, $this->getAuthKey());

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return [
                'valid' => false,
                'error' => $this->trans('Security verification failed. Please refresh the page and try again.')
            ];
        }

        // Check token expiration (5 minutes)
        $tokenAge = time() - $timestamp;
        if ($tokenAge > self::TOKEN_EXPIRY_SECONDS || $tokenAge < 0) {
            return [
                'valid' => false,
                'error' => $this->trans('Security token expired. Please refresh the page and try again.')
            ];
        }

        // Check token usage limit (atomic: lock prevents concurrent bypasses)
        $usageKey = self::CACHE_PREFIX_TOKEN . md5($token);
        $remainingTtl = max(1, self::TOKEN_EXPIRY_SECONDS - $tokenAge);
        $usageResult = $this->checkAndIncrementCounter($usageKey, self::MAX_TOKEN_USES, $remainingTtl);

        if ($usageResult['exceeded']) {
            return [
                'valid' => false,
                'error' => $this->trans('Security token exhausted. Please refresh the page and try again.')
            ];
        }

        // Rate limiting: max requests per minute per IP (atomic)
        $rateLimitKey = self::CACHE_PREFIX_RATE . md5($clientIp);
        $rateLimitResult = $this->checkAndIncrementCounter($rateLimitKey, self::RATE_LIMIT_PER_MINUTE, 60);

        if ($rateLimitResult['exceeded']) {
            return [
                'valid' => false,
                'error' => $this->trans('Too many requests. Please wait a moment before trying again.')
            ];
        }

        // Hourly limit: max requests per hour per IP (atomic)
        $hourlyKey = self::CACHE_PREFIX_HOURLY . md5($clientIp);
        $hourlyResult = $this->checkAndIncrementCounter($hourlyKey, self::HOURLY_LIMIT, 3600);

        if ($hourlyResult['exceeded']) {
            return [
                'valid' => false,
                'error' => $this->trans('Request limit reached. Please try again later.')
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Atomically check a counter against a limit and increment if not exceeded.
     *
     * Uses file locking to prevent concurrent requests from bypassing the limit.
     *
     * @param string $cacheKey Cache key for the counter
     * @param int $limit Maximum allowed count
     * @param int $ttl Cache TTL in seconds
     * @return array{count: int, exceeded: bool}
     */
    private function checkAndIncrementCounter(string $cacheKey, int $limit, int $ttl): array
    {
        $filePath = $this->getCacheFilePath($cacheKey);
        $lockPath = $filePath . '.lock';

        // Create lock file and acquire exclusive lock
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            // Fail closed: deny when limiter state cannot be verified
            return ['count' => $limit, 'exceeded' => true, 'reason' => 'lock_open_failed'];
        }

        $lockAcquired = false;
        $startTime = time();

        // Try to acquire lock with timeout
        while (!$lockAcquired && (time() - $startTime) < self::LOCK_TIMEOUT_SECONDS) {
            $lockAcquired = flock($lockHandle, LOCK_EX | LOCK_NB);
            if (!$lockAcquired) {
                usleep(10000); // Sleep 10ms before retry
            }
        }

        if (!$lockAcquired) {
            fclose($lockHandle);
            // Fail closed on lock timeout
            return ['count' => $limit, 'exceeded' => true, 'reason' => 'lock_timeout'];
        }

        try {
            $data = $this->readCacheFile($filePath);
            $count = 0;

            if ($data !== null) {
                // Check if cache has expired
                if (isset($data['expires_at']) && $data['expires_at'] > time()) {
                    $count = (int) ($data['count'] ?? 0);
                }
            }

            if ($count >= $limit) {
                return ['count' => $count, 'exceeded' => true];
            }

            // Increment counter and save
            $newData = [
                'count' => $count + 1,
                'expires_at' => time() + $ttl,
                'created_at' => $data['created_at'] ?? time(),
            ];

            $this->writeCacheFile($filePath, $newData);

            return ['count' => $count, 'exceeded' => false];
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Read cache file data.
     *
     * @param string $filePath Path to cache file
     * @return array|null Cached data or null if not found/expired
     */
    private function readCacheFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Write data to cache file.
     *
     * @param string $filePath Path to cache file
     * @param array $data Data to cache
     * @return bool Success status
     */
    private function writeCacheFile(string $filePath, array $data): bool
    {
        $content = json_encode($data);
        if ($content === false) {
            return false;
        }

        // Write to temp file first, then rename for atomicity
        $tempPath = $filePath . '.tmp.' . getmypid();
        $result = file_put_contents($tempPath, $content, LOCK_EX);

        if ($result === false) {
            return false;
        }

        return rename($tempPath, $filePath);
    }

    /**
     * Get cache file path for a given key.
     *
     * @param string $key Cache key
     * @return string Full file path
     */
    private function getCacheFilePath(string $key): string
    {
        // Use subdirectories based on first 2 chars of hash to avoid too many files in one directory
        $hash = md5($key);
        $subDir = substr($hash, 0, 2);
        $dir = $this->cacheDir . DIRECTORY_SEPARATOR . $subDir;

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    /**
     * Get the cache directory path.
     *
     * @return string Cache directory path
     */
    private function getCacheDirectory(): string
    {
        // Use module's var directory for cache
        $moduleDir = _PS_MODULE_DIR_ . 'globalpayments' . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '3ds_security';

        return $moduleDir;
    }

    /**
     * Ensure cache directory exists.
     *
     * @return void
     */
    private function ensureCacheDirectoryExists(): void
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);

            // Create .htaccess to prevent direct access
            $htaccessPath = $this->cacheDir . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccessPath)) {
                file_put_contents($htaccessPath, "Deny from all\n");
            }

            // Create index.php for security
            $indexPath = $this->cacheDir . DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($indexPath)) {
                file_put_contents($indexPath, "<?php\n// Silence is golden.\n");
            }
        }
    }

    /**
     * Get the client IP address using PrestaShop's trusted IP resolution.
     *
     * Uses Tools::getRemoteAddr() which only trusts proxy headers (X-Forwarded-For)
     * when REMOTE_ADDR is a private/local IP, preventing header spoofing attacks.
     *
     * @return string
     */
    public function getClientIp(): string
    {
        $ip = \Tools::getRemoteAddr();

        if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }

    /**
     * Get the secret key for IP hash.
     *
     * @return string
     */
    private function getSecretKey(): string
    {
        return _COOKIE_KEY_ . 'gp_3ds_secure_auth';
    }

    /**
     * Get the authentication key for HMAC signing.
     *
     * @return string
     */
    private function getAuthKey(): string
    {
        return _COOKIE_KEY_ . 'gp_3ds_auth';
    }

    /**
     * Translate a message.
     *
     * @param string $message The message to translate
     * @return string Translated message
     */
    private function trans(string $message): string
    {
        if ($this->translator !== null) {
            return $this->translator->trans($message, [], 'Modules.Globalpayments.Shop');
        }

        return $message;
    }

    /**
     * Cleanup expired cache files.
     *
     * Should be called periodically (e.g., via cron or randomly during requests).
     *
     * @return void
     */
    public function cleanupExpiredCache(): void
    {
        // Only cleanup 1% of the time to reduce load
        if (mt_rand(1, 100) > 1) {
            return;
        }

        $this->cleanupDirectory($this->cacheDir);
    }

    /**
     * Recursively cleanup expired cache files in a directory.
     *
     * @param string $dir Directory path
     * @return void
     */
    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $now = time();
        $files = @scandir($dir);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.php') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->cleanupDirectory($path);
            } elseif (substr($file, -6) === '.cache') {
                $data = $this->readCacheFile($path);
                if ($data !== null && isset($data['expires_at']) && $data['expires_at'] < $now) {
                    @unlink($path);
                    // Also remove lock file if exists
                    @unlink($path . '.lock');
                }
            }
        }
    }
}
