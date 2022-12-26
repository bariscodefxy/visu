<?php

namespace VISU\Graphics\Rendering;

use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Resource\RenderTargetResource;
use VISU\Graphics\Rendering\Resource\TextureResource;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\Texture;
use VISU\Graphics\TextureOptions;

class RenderPipeline
{
    /**
     * An array of render passes
     *
     * @var array<RenderPass>
     */
    private array $passes = [];

    /**
     * An array of render resources
     * 
     * @var array<RenderResource>
     */
    private array $resources = [];

    /**
     * A internal counter for the next resource handle
     * 
     * @var int
     */
    private int $resourceHandleIndex = 0;

    /**
     * Constrcutor
     */
    public function __construct(
        private PipelineResources $resourceAllocator,
        private PipelineContainer $data,
        private RenderTarget $backbuffer
    )
    {
        $backbufferData = $data->create(BackbufferData::class);
        $backbufferData->target = $this->importRenderTarget('backbuffer', $this->backbuffer);

        // activate the backbuffer as the default render target
        $resourceAllocator->activateRenderTarget($backbufferData->target);
    }

    /**
     * Returns a new resource handle
     * 
     * @return int
     */
    private function nextResourceHandle(): int
    {
        return $this->resourceHandleIndex++;
    }

    /**
     * Creates a new render resource of given type with name
     * 
     * @template T of RenderResource
     * @param class-string<T> $type
     * @param string $resourceName
     * @param mixed ...$args
     * 
     * @return T|RenderResource
     */
    private function createResource(string $type, string $resourceName, ...$args): RenderResource
    {
        $handle = $this->nextResourceHandle();
        $this->resources[$handle] = new $type($handle, $resourceName, ...$args);
        return $this->resources[$handle];
    }

    /**
     * Creates a new render target resource
     * 
     * @param string $resourceName 
     * @param int $width 
     * @param int $height 
     * 
     * @return RenderTargetResource 
     */
    public function createRenderTarget(string $resourceName, int $width, int $height): RenderTargetResource
    {
        /** @var RenderTargetResource */
        $resource = $this->createResource(RenderTargetResource::class, $resourceName);
        $resource->width = $width;
        $resource->height = $height;
        
        return $resource;
    }

    /**
     * Imports a render target resource
     * 
     * @param string $resourceName 
     * @param RenderTarget $target 
     * 
     * @return RenderTargetResource 
     */
    public function importRenderTarget(string $resourceName, RenderTarget $target): RenderTargetResource
    {
        /** @var RenderTargetResource */
        $resource = $this->createResource(RenderTargetResource::class, $resourceName);
        $resource->width = $target->width();
        $resource->height = $target->height();

        $this->resourceAllocator->setRenderTarget($resource, $target);

        return $resource;
    }

    /**
     * Createa a color attachment for a render target resource
     * 
     * @param RenderTargetResource $target
     * @param string $name
     * @param TextureOptions|null $options Optional texture options for the attachment
     * 
     * @return TextureResource
     */
    public function createColorAttachment(RenderTargetResource $target, string $name, ?TextureOptions $options = null): TextureResource
    {   
        /** @var TextureResource */
        $resource = $this->createResource(TextureResource::class, $target->name . '.attachment.color_' . $name, $target->width, $target->height, $options);

        $target->colorAttachments[] = $resource;

        return $resource;
    }

    /**
     * Imports a texture resource
     * 
     * @param string $resourceName
     * @param Texture $texture
     * 
     * @return TextureResource
     */
    public function importTexture(string $resourceName, Texture $texture): TextureResource
    {
        /** @var TextureResource */
        $resource = $this->createResource(TextureResource::class, $resourceName, $texture->width(), $texture->height());

        $this->resourceAllocator->setTexture($resource, $texture);

        return $resource;
    }

    /**
     * Adds a new render pass to the pipeline
     * 
     * @param RenderPass $pass
     */
    public function addPass(RenderPass $pass): void
    {
        $this->passes[] = $pass;
        
        // run render pass setup
        $pass->setup($this, $this->data);
    }

    /**
     * Execute the render pipeline
     * 
     * @param int $tickIndex Lets the pipeline know which tick it is.
     *                       This is used to determine the order in which this pipeline is executed.
     *                       The paramter is rather important as for example the garbage collector
     *                       uses this to determine when to free resources.
     * @return void 
     */
    public function execute(int $tickIndex): void
    {
        $this->resourceAllocator->setCurrentTick($tickIndex);

        foreach ($this->passes as $pass) {
            $pass->execute($this->data, $this->resourceAllocator);
        }

        $this->resourceAllocator->collectGarbage();
    }
}
