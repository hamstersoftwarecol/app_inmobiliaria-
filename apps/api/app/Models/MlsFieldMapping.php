<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['internal_field', 'provider_field'])]
class MlsFieldMapping extends Model {}
