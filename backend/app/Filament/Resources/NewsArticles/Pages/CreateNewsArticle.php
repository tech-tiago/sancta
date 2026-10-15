<?php

namespace App\Filament\Resources\NewsArticles\Pages;

use App\Filament\Resources\NewsArticles\NewsArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;
}
