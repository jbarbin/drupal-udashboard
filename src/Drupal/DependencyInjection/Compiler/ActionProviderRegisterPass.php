<?php

namespace MakinaCorpus\Dashboard\Drupal\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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
            $interface = '\MakinaCorpus\Dashboard\Drupal\Action\ActionProviderInterface';

            if (!$refClass->implementsInterface($interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            $definition->addMethodCall('register', [new Reference($id)]);
        }

        if (!$container->hasDefinition('udashboard.processor_registry')) {
            return;
        }
        $definition = $container->getDefinition('udashboard.processor_registry');

        // Register automatic action provider based on action processors
        $taggedServices = $container->findTaggedServiceIds('udashboard.action');
        foreach ($taggedServices as $id => $attributes) {
            $def = $container->getDefinition($id);

            $class = $container->getParameterBag()->resolveValue($def->getClass());
            $refClass = new \ReflectionClass($class);
            $parentClass = '\MakinaCorpus\Dashboard\Drupal\Action\AbstractActionProcessor';

            if (!$refClass->isSubclassOf($parentClass)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement extend "%s".', $id, $parentClass));
            }

            $definition->addMethodCall('register', [new Reference($id)]);
        }
    }
}
