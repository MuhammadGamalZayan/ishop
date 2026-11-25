<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** An e-mail from the newsletter forms (home page and footer). */
#[Fillable(['email', 'locale'])]
class NewsletterSubscriber extends Model
{
    //
}
