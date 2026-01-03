<?php

/**
 * @file plugins/generic/emspubcore/classes/RateLimiter.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class RateLimiter
 *
 * @ingroup plugins_generic_emspubcore
 *
 * @brief Simple file-based rate limiter for webhook endpoints
 */

namespace APP\plugins\generic\emspubcore\classes;

class RateLimiter
{
    /** @var string Directory for rate limit files */
    private string $storageDir;
    
    /** @var int Maximum requests allowed in the time window */
    private int $maxRequests;
    
    /** @var int Time window in seconds */
    private int $windowSeconds;

    /**
     * Constructor
     *
     * @param int $maxRequests Maximum requests per window (default: 60)
     * @param int $windowSeconds Time window in seconds (default: 60)
     */
    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        
        // Use system temp directory with a plugin subdirectory
        $this->storageDir = sys_get_temp_dir() . '/emspubcore_ratelimit';
        
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Check if the request is within rate limits
     *
     * @param string|null $identifier IP address or other identifier (defaults to client IP)
     * @return bool True if request is allowed, false if rate limited
     */
    public function check(?string $identifier = null): bool
    {
        $identifier = $identifier ?? $this->getClientIp();
        $key = md5($identifier);
        $file = $this->storageDir . '/' . $key . '.json';
        
        $now = time();
        $data = $this->loadData($file);
        
        // Clean up old entries outside the window
        $data = array_filter($data, fn($timestamp) => ($now - $timestamp) < $this->windowSeconds);
        
        // Check if limit exceeded
        if (count($data) >= $this->maxRequests) {
            return false;
        }
        
        // Add current request
        $data[] = $now;
        $this->saveData($file, $data);
        
        return true;
    }

    /**
     * Get the number of remaining requests for an identifier
     *
     * @param string|null $identifier
     * @return int
     */
    public function remaining(?string $identifier = null): int
    {
        $identifier = $identifier ?? $this->getClientIp();
        $key = md5($identifier);
        $file = $this->storageDir . '/' . $key . '.json';
        
        $now = time();
        $data = $this->loadData($file);
        $data = array_filter($data, fn($timestamp) => ($now - $timestamp) < $this->windowSeconds);
        
        return max(0, $this->maxRequests - count($data));
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        // Check for forwarded IP (behind proxy/load balancer)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Load rate limit data from file
     */
    private function loadData(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save rate limit data to file
     */
    private function saveData(string $file, array $data): void
    {
        @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    }

    /**
     * Clean up old rate limit files (call periodically)
     */
    public function cleanup(): void
    {
        if (!is_dir($this->storageDir)) {
            return;
        }
        
        $files = glob($this->storageDir . '/*.json');
        $now = time();
        
        foreach ($files as $file) {
            // Delete files older than 2x the window
            if (filemtime($file) < ($now - $this->windowSeconds * 2)) {
                @unlink($file);
            }
        }
    }
}
