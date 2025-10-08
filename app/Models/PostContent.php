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
        'processing_error'
    ];

    /**
     * Get the video URL (HLS or regular)
     *
     * @return string
     */
    public function getVideoUrl()
    {
        // If HLS is ready, return HLS path
        if ($this->is_hls && $this->hls_path && $this->processing_status === 'completed') {
            return $this->hls_path;
        }

        // Otherwise return original content path
        return $this->content;
    }
}
