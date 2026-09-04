<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Strategies\MethodCategory;
use App\Domain\Strategies\MethodCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MethodCode $code
 * @property MethodCategory $category
 */
class Method extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['code' => MethodCode::class, 'category' => MethodCategory::class, 'is_active' => 'boolean'];
    }

    /** @return HasMany<MethodVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(MethodVersion::class);
    }
}
