<?php

namespace VISU\Graphics\Rendering\Resource;

use VISU\Graphics\Rendering\RenderResource;

class RenderTargetResource extends RenderResource
{
    /**
     * The render targets width (in pixels)
     */
    public int $width = 1280;

    /**
     * The render targets height (in pixels)
     */
    public int $height = 720;

    /**
     * An array of TextureResource objects that are attached to the render target
     * 
     * @var array<int, TextureResource>
     */
    public array $colorAttachments = [];

    /**
     * Depth attachment
     */
    public ?TextureResource $depthAttachment = null;

    /**
     * Create a Renderbuffer
     */
    public bool $createRenderbuffer = false;
}
