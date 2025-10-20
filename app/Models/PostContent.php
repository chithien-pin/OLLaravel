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
        'is_hls',
        'hls_path',
        'processing_status',
        'processing_error',
        // Cloudflare Stream fields
        'cloudflare_video_id',
        'cloudflare_stream_url',
        'cloudflare_thumbnail_url',
        'cloudflare_hls_url',
        'cloudflare_dash_url',
        'cloudflare_status',
        'cloudflare_error',
        'cloudflare_duration',
        'cloudflare_upload_id'
    ];

    /**
     * Get the video URL (Cloudflare Stream, HLS or regular)
     *
     * @return string
     */
    public function getVideoUrl()
    {
        // If Cloudflare Stream is ready, return Cloudflare HLS URL
        if ($this->cloudflare_status === 'ready' && $this->cloudflare_hls_url) {
            return $this->cloudflare_hls_url;
        }

        // If local HLS is ready, return HLS path (legacy support)
        if ($this->is_hls && $this->hls_path && $this->processing_status === 'completed') {
            return $this->hls_path;
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
               $this->cloudflare_status === 'uploading' ||
               $this->processing_status === 'processing';
    }

    /**
     * Transform content for API response
     * This method updates the content URL based on video processing status
     *
     * @return void
     */
    public function transformForResponse()
    {
        if ($this->content_type == 1) { // Video type
            // Priority 1: Use Cloudflare Stream if ready
            if ($this->cloudflare_status === 'ready' && $this->cloudflare_hls_url) {
                $this->content = $this->cloudflare_hls_url;
                $this->is_cloudflare_stream = true;
                $this->is_hls_stream = false;

                // Add Cloudflare-specific fields to response
                $this->cloudflare_video_url = $this->cloudflare_hls_url;
                $this->cloudflare_thumbnail = $this->cloudflare_thumbnail_url;

            // Priority 2: Use local HLS if ready (legacy support)
            } elseif ($this->is_hls && $this->hls_path && $this->processing_status === 'completed') {
                $this->content = '/storage/' . $this->hls_path;
                $this->is_hls_stream = true;
                $this->is_cloudflare_stream = false;

            // Priority 3: Use original file (fallback)
            } else {
                // Keep original content path
                $this->is_hls_stream = false;
                $this->is_cloudflare_stream = false;
            }

            // Add processing status info
            if ($this->cloudflare_status === 'processing' || $this->cloudflare_status === 'uploading') {
                $this->is_processing = true;
                $this->processing_message = 'Video is being processed, please check back later';
            } else {
                $this->is_processing = false;
            }
        }

        // Use Cloudflare thumbnail if available
        if ($this->cloudflare_thumbnail_url) {
            $this->thumbnail = $this->cloudflare_thumbnail_url;
        }
    }
}
