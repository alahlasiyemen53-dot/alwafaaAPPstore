<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

final class MediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        if ($this->getDiskName() !== 'public') {
            return parent::getUrl();
        }

        $path = $this->getUrlEncodedPathRelativeToRoot();
        $url = asset('storage/'.$path);

        return $this->versionUrl($url);
    }

    public function getBaseMediaDirectoryUrl(): string
    {
        if ($this->getDiskName() !== 'public') {
            return parent::getBaseMediaDirectoryUrl();
        }

        return asset('storage');
    }

    public function getResponsiveImagesDirectoryUrl(): string
    {
        if ($this->getDiskName() !== 'public') {
            return parent::getResponsiveImagesDirectoryUrl();
        }

        $path = $this->pathGenerator->getPathForResponsiveImages($this->media);

        return Str::finish(asset('storage/'.$path), '/');
    }
}
