<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function findings(){
        return $this->hasMany(Findings::class);
    }

    public static function boot() {
        parent::boot();

        static::deleting(function($project) { // Before delete() method is called
            $project->findings()->delete();
        });
    }
}
