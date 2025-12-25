<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostMedia extends Model
{
    use HasFactory;

    protected $table = 'post_media';

    protected $fillable = [
        'post_id',
        'media_type',
        'r2_id',
        'r2_status',
        'r2_error',
        'original_filename',
        'mime_type',
        'file_size',
        'r2_raw_path',
        'r2_hls_url',
        'r2_thumbnail_url',
        'r2_image_variants',
        'duration',
        'width',
        'height',
        'has_audio',
        'transcode_job_id',
        'transcode_progress',
        'sort_order',
        'blurhash',
        'aspect_ratio',
        'view_count',
    ];

    protected $casts = [
        'r2_image_variants' => 'array',
        'has_audio' => 'boolean',
    ];

    /**
     * Get the post that owns this media.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Check if media is ready for display
     *
     * @return bool
     */
    public function isReady()
    {
        return $this->r2_status === 'ready';
    }

    /**
     * Check if media is still processing
     *
     * @return bool
     */
    public function isProcessing()
    {
        return in_array($this->r2_status, ['processing', 'transcoding', 'uploading']);
    }

    /**
     * Check if media has error
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->r2_status === 'error';
    }

    /**
     * Get video URL (HLS master playlist)
     *
     * @return string|null
     */
    public function getVideoUrl()
    {
        if ($this->media_type !== 1) {
            return null;
        }

        return $this->isReady() ? $this->r2_hls_url : null;
    }

    /**
     * Get image URL for specific variant
     *
     * @param string $variant
     * @return string|null
     */
    public function getImageUrl($variant = 'large')
    {
        if ($this->media_type !== 0) {
            return null;
        }

        $variants = $this->r2_image_variants ?? [];
        return $variants[$variant] ?? $variants['large'] ?? null;
    }

    /**
     * Get thumbnail URL
     *
     * @return string|null
     */
    public function getThumbnailUrl()
    {
        // Video thumbnail
        if ($this->media_type === 1) {
            return $this->r2_thumbnail_url;
        }

        // Image thumbnail variant
        $variants = $this->r2_image_variants ?? [];
        return $variants['thumbnail'] ?? $this->getImageUrl('large');
    }

    /**
     * Transform for API response - MATCHES PostContent format exactly!
     * Flutter DOES NOT need to change anything.
     *
     * @return void
     */
    public function transformForResponse()
    {
        // Video (media_type = 1)
        if ($this->media_type == 1) {
            if ($this->isReady()) {
                $this->content = $this->r2_hls_url;
                $this->thumbnail = $this->r2_thumbnail_url;
                $this->setAttribute('is_r2_video', true);
            } else {
                // Still processing - keep null/empty
                $this->content = null;
                $this->thumbnail = null;
            }

            $this->content_type = 1;
            $this->setAttribute('is_processing', $this->isProcessing());

            if ($this->isProcessing()) {
                $this->setAttribute('processing_message', 'Video is being processed, please check back later');
                $this->setAttribute('processing_progress', $this->transcode_progress);
            }

            if ($this->hasError()) {
                $this->setAttribute('has_error', true);
                $this->setAttribute('error_message', $this->r2_error);
            }
        }
        // Image (media_type = 0)
        else {
            $variants = $this->r2_image_variants ?? [];

            // Use large for main content, thumbnail for thumbnail
            $this->content = $variants['large'] ?? $variants['medium'] ?? null;
            $this->thumbnail = $variants['thumbnail'] ?? $this->content;
            $this->content_type = 0;
            $this->setAttribute('is_r2_image', true);

            // Expose all variants for frontend optimization
            $this->setAttribute('r2_image_thumbnail', $variants['thumbnail'] ?? null);
            $this->setAttribute('r2_image_medium', $variants['medium'] ?? null);
            $this->setAttribute('r2_image_large', $variants['large'] ?? null);
        }
    }
}
