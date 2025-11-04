<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostContent extends Model
{
    use HasFactory;
    public $table = "post_contents";

    protected $fillable = [
        'post_id',
        'content',
        'thumbnail',
        'content_type',
        // Cloudflare Stream fields
        'cloudflare_video_id',
        'cloudflare_stream_url',
        'cloudflare_thumbnail_url',
        'cloudflare_hls_url',
        'cloudflare_dash_url',
        'cloudflare_status',
        'cloudflare_error',
        'cloudflare_duration',
        'cloudflare_upload_id',
        // Cloudflare Images fields
        'cloudflare_image_id',
        'cloudflare_image_url',
        'cloudflare_image_variants',
        // R2 Storage fields
        'r2_mp4_url',
        'r2_key',
        'r2_file_size',
        'r2_uploaded_at',
        'use_r2',
        'r2_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'cloudflare_image_variants' => 'array',
    ];

    /**
     * Get the video URL (R2 MP4 > Cloudflare Stream HLS > original)
     *
     * @return string
     */
    public function getVideoUrl()
    {
        // Priority 1: R2 MP4 if available and preferred (FREE bandwidth!)
        if ($this->shouldUseR2() && $this->r2_mp4_url) {
            return $this->r2_mp4_url;
        }

        // Priority 2: Cloudflare Stream HLS
        if ($this->cloudflare_status === 'ready' && $this->cloudflare_hls_url) {
            return $this->cloudflare_hls_url;
        }

        // Priority 3: Original content path (fallback)
        return $this->content;
    }

    /**
     * Check if should use R2 for this video
     *
     * @return bool
     */
    public function shouldUseR2()
    {
        // If R2 is not ready, don't use it
        if ($this->r2_status !== 'ready' || empty($this->r2_mp4_url)) {
            return false;
        }

        // If use_r2 is explicitly set, use that preference
        if ($this->use_r2 !== null) {
            return (bool)$this->use_r2;
        }

        // Default strategy: Use R2 for videos older than 7 days
        $videoAge = now()->diffInDays($this->created_at);
        return $videoAge > 7;
    }

    /**
     * Check if R2 is available for this video
     *
     * @return bool
     */
    public function isR2Available()
    {
        return !empty($this->r2_mp4_url) && $this->r2_status === 'ready';
    }

    /**
     * Get the preferred video source
     *
     * @return string 'r2'|'stream'|'local'
     */
    public function getVideoSource()
    {
        if ($this->shouldUseR2() && $this->r2_mp4_url) {
            return 'r2';
        }

        if ($this->cloudflare_status === 'ready' && $this->cloudflare_hls_url) {
            return 'stream';
        }

        return 'local';
    }

    /**
     * Check if video is using Cloudflare Stream
     *
     * @return bool
     */
    public function isCloudflareStream()
    {
        return !empty($this->cloudflare_video_id) && $this->cloudflare_status === 'ready';
    }

    /**
     * Check if image is using Cloudflare Images
     *
     * @return bool
     */
    public function isCloudflareImage()
    {
        return !empty($this->cloudflare_image_id);
    }

    /**
     * Get image URL for a specific variant
     *
     * @param string $variant Variant name (thumbnail, medium, large, public)
     * @return string|null
     */
    public function getImageUrl($variant = 'public')
    {
        if (!$this->isCloudflareImage()) {
            return null;
        }

        if (isset($this->cloudflare_image_variants[$variant])) {
            return $this->cloudflare_image_variants[$variant];
        }

        return $this->cloudflare_image_url;
    }

    /**
     * Get thumbnail URL (Cloudflare or local)
     *
     * @return string|null
     */
    public function getThumbnailUrl()
    {
        // Use Cloudflare thumbnail if available
        if ($this->cloudflare_thumbnail_url) {
            return $this->cloudflare_thumbnail_url;
        }

        // Otherwise use local thumbnail
        return $this->thumbnail;
    }

    /**
     * Check if video is still processing
     *
     * @return bool
     */
    public function isProcessing()
    {
        return $this->cloudflare_status === 'processing' ||
               $this->cloudflare_status === 'uploading';
    }

    /**
     * Transform content for API response
     * This method updates the content URL based on video/image processing status
     *
     * @return void
     */
    public function transformForResponse()
    {
        if ($this->content_type == 1) { // Video type
            // Use Cloudflare Stream if ready
            if ($this->cloudflare_status === 'ready' && $this->cloudflare_hls_url) {
                $this->content = $this->cloudflare_hls_url;
                $this->setAttribute('is_cloudflare_stream', true);

                // Add Cloudflare-specific fields to response
                $this->setAttribute('cloudflare_video_url', $this->cloudflare_hls_url);
                $this->setAttribute('cloudflare_thumbnail', $this->cloudflare_thumbnail_url);
            } else {
                // Keep original content path (fallback)
                $this->setAttribute('is_cloudflare_stream', false);
            }

            // Add processing status info
            if ($this->cloudflare_status === 'processing' || $this->cloudflare_status === 'uploading') {
                $this->setAttribute('is_processing', true);
                $this->setAttribute('processing_message', 'Video is being processed, please check back later');
            } else {
                $this->setAttribute('is_processing', false);
            }

            // Use Cloudflare thumbnail if available
            if ($this->cloudflare_thumbnail_url) {
                $this->thumbnail = $this->cloudflare_thumbnail_url;
            }

            // Add R2 fields to API response using setAttribute() for proper JSON serialization
            $this->setAttribute('is_r2_available', $this->isR2Available());
            $this->setAttribute('video_source', $this->getVideoSource());
            // r2_mp4_url is already a database column, it will be included automatically
        } elseif ($this->content_type == 0) { // Image type
            // Use Cloudflare Images if available
            if ($this->isCloudflareImage()) {
                // Use public variant for main content
                $this->content = $this->cloudflare_image_variants['public'] ?? $this->cloudflare_image_url;

                // Use thumbnail variant for thumbnail
                $this->thumbnail = $this->cloudflare_image_variants['thumbnail'] ?? $this->content;

                $this->setAttribute('is_cloudflare_image', true);

                // Add all variant URLs to response
                $this->setAttribute('cloudflare_image_thumbnail', $this->cloudflare_image_variants['thumbnail'] ?? null);
                $this->setAttribute('cloudflare_image_medium', $this->cloudflare_image_variants['medium'] ?? null);
                $this->setAttribute('cloudflare_image_large', $this->cloudflare_image_variants['large'] ?? null);
            } else {
                // Legacy: Local storage images
                $this->setAttribute('is_cloudflare_image', false);

                // Keep original content and thumbnail paths
                // (will be transformed to full URLs by GlobalFunction::createMediaUrl elsewhere)
            }
        }
    }
}
