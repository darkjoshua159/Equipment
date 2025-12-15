<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * These match the columns you defined in your migration.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'description',
        'image',
        // 'user_id', // Uncomment this if you decided to add the user_id foreign key
    ];

    /**
     * The attributes that should be cast to native types.
     * Ensures 'price' is always treated as a float/decimal in PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2', // Casts the price to a decimal with 2 places
    ];

    /**
     * Define the relationship: An Equipment belongs to one User (the one who added it).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}