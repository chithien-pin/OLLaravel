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
     * Get the video URL (Cloudflare Stream or original)
     *
     * @return string
     */
    public function getVideoUrl()
    {
        // If Cloudflare Stream is ready, return Cloudflare HLS URL
        if ($this->cloudflare_status === 'ready' && $this->cloudflare_hls_url) {
            return $this->cloudflare_hls_url;
        }

        // Otherwise return original content path
        return $this->content;
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
                $this->is_cloudflare_stream = true;

                // Add Cloudflare-specific fields to response
                $this->cloudflare_video_url = $this->cloudflare_hls_url;
                $this->cloudflare_thumbnail = $this->cloudflare_thumbnail_url;
            } else {
                // Keep original content path (fallback)
                $this->is_cloudflare_stream = false;
            }

            // Add processing status info
            if ($this->cloudflare_status === 'processing' || $this->cloudflare_status === 'uploading') {
                $this->is_processing = true;
                $this->processing_message = 'Video is being processed, please check back later';
            } else {
                $this->is_processing = false;
            }

            // Use Cloudflare thumbnail if available
            if ($this->cloudflare_thumbnail_url) {
                $this->thumbnail = $this->cloudflare_thumbnail_url;
            }
        } elseif ($this->content_type == 0) { // Image type
            // Use Cloudflare Images if available
            if ($this->isCloudflareImage()) {
                // Use public variant for main content
                $this->content = $this->cloudflare_image_variants['public'] ?? $this->cloudflare_image_url;

                // Use thumbnail variant for thumbnail
                $this->thumbnail = $this->cloudflare_image_variants['thumbnail'] ?? $this->content;

                $this->is_cloudflare_image = true;

                // Add all variant URLs to response
                $this->cloudflare_image_thumbnail = $this->cloudflare_image_variants['thumbnail'] ?? null;
                $this->cloudflare_image_medium = $this->cloudflare_image_variants['medium'] ?? null;
                $this->cloudflare_image_large = $this->cloudflare_image_variants['large'] ?? null;
            } else {
                // Legacy: Local storage images
                $this->is_cloudflare_image = false;

                // Keep original content and thumbnail paths
                // (will be transformed to full URLs by GlobalFunction::createMediaUrl elsewhere)
            }
        }
    }
}
