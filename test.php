<?php

require __DIR__ . '/vendor/autoload.php';

use GL\Math\Vec4;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\Pass\FullscreenQuadPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;
use VISU\Graphics\Texture;
use VISU\OS\Input;
use VISU\OS\Window;
use VISU\Runtime\GameLoop;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\KeySignal;

glfwInit();

$gl = new GLState;
$window = new Window('Test Window', 640, 480);
$window->initailize($gl);

$dispatcher = new Dispatcher;
$input = new Input($window, $dispatcher);

$window->setEventHandler($input);

$dispatcher->register('input.key', function (KeySignal $signal) {
    var_dump($signal->scancode);
});

$testTexture  = new Texture('test');
// $testTexture->loadFromFile(__DIR__ . '/resources/fonts/cozette/cozette.png');
$testTexture->loadFromFile(__DIR__ . '/resources/phplogo.png');

// declare a simple shader to render the texture
$shader = new ShaderProgram($gl);
$shader->attach(new ShaderStage(GL_VERTEX_SHADER, <<<EOT
#version 330 core

layout (location = 0) in vec3 aPos;
layout (location = 1) in vec2 aTexCoord;

out vec2 TexCoords;

void main()
{
    gl_Position = vec4(aPos, 1.0);
    TexCoords = aTexCoord;
}
EOT
));
$shader->attach(new ShaderStage(GL_FRAGMENT_SHADER, <<<EOT
#version 330 core

out vec4 FragColor;
in vec2 TexCoords;

uniform sampler2D u_texture;

void main()
{             
    FragColor = texture(u_texture, TexCoords);
}
EOT
));

$shader->link();


class Game implements VISU\Runtime\GameLoopDelegate
{
    private GameLoop $loop;
    private PipelineResources $renderResources; 

    private int $tick = 0;

    public function __construct(
        private Window $window,
        private GLState $gl,
    )
    {
        $this->loop = new GameLoop($this);
        $this->renderResources = new PipelineResources($gl);
    }

    public function update(): void
    {
        $this->window->pollEvents();
    }

    public function render(float $delta): void
    {
        $windowRenderTarget = $this->window->getRenderTarget();
        $windowRenderTarget->framebuffer()->clearColor = new Vec4(1, 0.2, 0.2, 1.0);

        $data = new PipelineContainer;

        $pipeline = new RenderPipeline($this->renderResources, $data, $windowRenderTarget);

        global $testTexture, $shader;
        $texRes = $pipeline->importTexture('test', $testTexture);

        $pipeline->addPass(new ClearPass($data->get(BackbufferData::class)->target));
        $pipeline->addPass(new FullscreenQuadPass($data->get(BackbufferData::class)->target, $texRes, $shader));

        // $pipeline->addPass(new ShadowMapPass($renderables));

        // $pipeline->addPass(new DebugDepthPass($data->get(ShadowMapData::class)->shadowMap));

        $pipeline->execute($this->tick++);

        $this->window->swapBuffers();
    }

    public function shouldStop(): bool
    {
        return $this->window->shouldClose();
    }

    public function start(): void
    {
        $this->loop->start();
    }
}

$game = new Game($window, $gl);
$game->start();


// while (!$window->shouldClose()) {
//     $window->pollEvents();
//     $window->swapBuffers();
// }