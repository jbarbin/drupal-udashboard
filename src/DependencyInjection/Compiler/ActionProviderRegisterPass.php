<?php

namespace MakinaCorpus\Dashboard\DependencyInjection\Compiler;

use MakinaCorpus\Dashboard\Action\ActionProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers action providers into the action registry
 */
class ActionProviderRegisterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('udashboard.action_provider_registry')) {
            return;
        }
        $definition = $container->getDefinition('udashboard.action_provider_registry');

        // Register custom action providers
        $taggedServices = $container->findTaggedServiceIds('udashboard.action_provider');
        foreach ($taggedServices as $id => $attributes) {
            $def = $container->getDefinition($id);

            $class = $container->getParameterBag()->resolveValue($def->getClass());
            $refClass = new \ReflectionClass($class);

            if (!$refClass->implementsInterface(ActionProviderInterface::class)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, ActionProviderInterface::class));
            }

            $definition->addMethodCall('register', [new Reference($id)]);
        }

        if (!$container->hasDefinition('udashboard.processor_registry')) {
            return;
        }
        $definition = $container->getDefinition('udashboard.processor_registry');
    }
}