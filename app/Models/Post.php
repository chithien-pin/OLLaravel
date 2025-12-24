<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;
    public $table = "posts";

    /**
     * Get post content (Cloudflare/legacy - old system)
     */
    public function content()
    {
        return $this->hasMany(PostContent::class, 'post_id', 'id');
    }

    /**
     * Get post media (R2 storage - new system)
     */
    public function r2Media()
    {
        return $this->hasMany(PostMedia::class, 'post_id', 'id');
    }

    public function user()
    {
        return $this->hasOne(Users::class, 'id','user_id');
    }

    /**
     * Get all media (combines legacy content + R2 media)
     * Returns merged collection with consistent format
     */
    public function getAllMedia()
    {
        $legacyContent = $this->content;
        $r2Media = $this->r2Media;

        // Transform both for response
        foreach ($legacyContent as $item) {
            $item->transformForResponse();
        }
        foreach ($r2Media as $item) {
            $item->transformForResponse();
        }

        // Merge: R2 takes priority if exists
        return $r2Media->count() > 0 ? $r2Media : $legacyContent;
    }
}
