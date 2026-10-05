<?php

declare(strict_types=1);

arch('support helpers are final')
    ->expect('App\Support')
    ->classes()
    ->toBeFinal();

arch('support helpers declare no abstract methods')
    ->expect('App\Support')
    ->classes()
    ->not->toBeAbstract();

/**
 * @return array<int, array{0: string}>
 */
function supportClasses(): array
{
    $directory = dirname(__DIR__, 2).'/app/Support';
    $dataset = [];

    foreach (glob($directory.'/*.php') ?: [] as $file) {
        $dataset[] = ['App\\Support\\'.basename($file, '.php')];
    }

    return $dataset;
}

it('keeps support helpers stateless', function (string $class): void {
    $reflection = new ReflectionClass($class);

    expect($reflection->getConstructor())->toBeNull();

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        expect($method->isStatic())->toBeTrue();
    }
})->with(supportClasses());
