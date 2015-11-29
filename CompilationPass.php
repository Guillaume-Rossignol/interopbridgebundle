<?php

namespace rgousi\InteropBridgeBundle;

use Interop\Container\Definition\FactoryCallDefinitionInterface;
use Interop\Container\Definition\ObjectDefinitionInterface;
use Interop\Container\Definition\ParameterDefinitionInterface;
use Interop\Container\Definition\ReferenceInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CompilationPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('interop.definitions.providers')) {
            return;
        }
        $definitionsProviders = $container->getParameter('interop.definitions.providers');
        foreach ($definitionsProviders as $definitionProvider) {
            $instance = new $definitionProvider;
            $definitions = $instance->getDefinitions();
            foreach ($definitions as $name => $definition) {
                if ($definition instanceof ParameterDefinitionInterface
                    && !$container->hasParameter($name)
                ) {
                    $container->setParameter($name, $definition->getValue());
                } elseif ($definition instanceof ObjectDefinitionInterface) {
                    $container->setDefinition($name, $this->convertObjectDefinition($container, $definition));
                } elseif ($definition instanceof FactoryCallDefinitionInterface) {
                    $container->setDefinition($name, $this->convertFactoryDefinition($container, $definition));
                }
            }
        }
    }

    private function convertObjectDefinition(ContainerBuilder $container, ObjectDefinitionInterface $definition)
    {
        $symfonyDefinition = new Definition();
        $symfonyDefinition->setClass($definition->getClassName());
        $symfonyDefinition->setArguments($this->convertArguments($container, $definition->getConstructorArguments()));

        foreach ($definition->getPropertyAssignments() as $propertyAssignment) {
            $symfonyDefinition->setProperty(
                $propertyAssignment->getPropertyName(),
                $this->convertArguments($container, $propertyAssignment->getValue())
            );
        }

        foreach ($definition->getMethodCalls() as $methodCall) {
            $symfonyDefinition->addMethodCall(
                $methodCall->getMethodName(),
                $this->convertArguments($container, $methodCall->getArguments())
            );
        }
        return $symfonyDefinition;
    }

    private function convertFactoryDefinition(ContainerBuilder $container, $definition)
    {
        $symfonyDefinition = new Definition('Class');
        $symfonyDefinition->setFactory([
            $this->convertArguments($container, $definition->getFactory()),
            $definition->getMethodName()
        ]);
        $symfonyDefinition->setArguments($this->convertArguments($container, $definition->getArguments()));
        return $symfonyDefinition;
    }

    private function convertArguments(ContainerBuilder $container, $arguments)
    {
        if (is_array($arguments)) {
            return array_map(
                [$this, 'convertArguments'],
                array_fill(0, count($arguments) , $container),
                $arguments
            );
        }
        if ($arguments instanceof ReferenceInterface) {
            if ($container->has($arguments->getTarget())) {
                return new Reference($arguments->getTarget());
            }
            if ($container->hasParameter($arguments->getTarget())) {
                return '%'.$arguments->getTarget().'%';
            }
            return null;
        }
        return $arguments;
    }
}