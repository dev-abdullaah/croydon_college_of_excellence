<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'filename',
        'description',
        'file_type',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Friendly download name, e.g. "Life in the UK Lessons 1-10.docx".
     */
    public function downloadName(): string
    {
        $title = preg_replace('/[^A-Za-z0-9 \-_.]/', '', $this->title) ?: 'course-material';

        return trim($title).'.'.$this->file_type;
    }

    /**
     * Is the file actually there to download?
     *
     * The .docx files were only ever present while the material was being laid
     * out. A catalogue row can outlive its file, and offering a buyer a link
     * that dead-ends is worse than saying plainly that the file is on its way.
     *
     * The same check guards delivery in CourseContentController, so this is a
     * question about what the page shows, not a security boundary.
     */
    public function fileExists(): bool
    {
        return is_file(base_path('course-files'.DIRECTORY_SEPARATOR.basename($this->filename)));
    }
}
