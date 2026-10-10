<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name_en', 'name_es', 'sort_order'])]
class PropertyType extends Model {}
