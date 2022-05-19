<?php

namespace ThumbnailProcessorImgProxy\Services;

use FroshThumbnailProcessor\Services\ThumbnailUrlTemplateInterface;
use Shopware\Bundle\MediaBundle\MediaServiceInterface;

class ThumbnailUrlTemplate implements ThumbnailUrlTemplateInterface
{
    /** @var string */
    private $imgProxyDomain;

    /** @var string */
    private $key;

    /** @var string */
    private $salt;

    /** @var string */
    private $resizingType;

    /** @var string */
    private $gravity;

    /** @var string */
    private $enlarge;

    /** @var string */
    private $extend;

    /** @var int */
    private $signatureSize;

    /** @var MediaServiceInterface */
    private MediaServiceInterface $mediaService;

    /** @var ThumbnailUrlTemplateInterface */
    private ThumbnailUrlTemplateInterface $parent;

    public function __construct(array $config, MediaServiceInterface $mediaService, ThumbnailUrlTemplateInterface $parent)
    {
        $this->imgProxyDomain = $config["ImgProxyDomain"];
        $this->key = $config['ImgProxyKey'];
        $this->salt = $config['ImgProxySalt'];
        $this->resizingType = $config['ResizingType'];
        $this->gravity = $config['Gravity'];
        $this->enlarge = $config['Enlarge'];
        $this->extend = $config['Extend'];
        $this->signatureSize = $config['SignatureSize'];

        $this->mediaService = $mediaService;
        $this->parent = $parent;
    }

    public function getUrl($mediaPath, $width, $height): string
    {
        $mediaUrl = substr($this->mediaService->getUrl('/'), 0, -1);
        $mediaPath = $this->mediaService->encode($mediaPath);

        $keyBin = pack('H*', $this->key);
        $saltBin = pack('H*', $this->salt);

        if (empty($keyBin) || empty($saltBin) || empty($this->imgproxyDomain) || $this->imgproxyDomain == 'https://imgproxy.example.com/') {
            return $this->parent->getUrl($mediaPath, $width, $height);
        }

        $encodedUrl = rtrim(strtr(base64_encode($mediaUrl . '/' . $mediaPath), '+/', '-_'), '=');

        $path = "/rs:{$this->resizingType}:{$width}:{$height}:{$this->enlarge}:{$this->extend}/g:{$this->gravity}/bg:255:255:255/{$encodedUrl}";
        $signature = hash_hmac("sha256", $saltBin . $path, $keyBin, true);

        if ($this->signatureSize !== 32) {
            $signature = pack('A' . $this->signatureSize, $signature);
        }

        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $this->imgproxyDomain . '/' . $signature . $path;
    }
}