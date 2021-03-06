<?php

namespace Yiisoft\Proxy;

final class ProxyManager
{
    private ?string $cachePath = null;

    private ClassRenderer $classRenderer;

    private ClassConfigurator $classConfigurator;

    private ClassCache $classCache;

    public function __construct(string $cachePath = null)
    {
        $this->cachePath = $cachePath;
        $this->classCache = new ClassCache($cachePath);
        $this->classRenderer = new ClassRenderer();
        $this->classConfigurator = new ClassConfigurator();
    }

    public function createObjectProxyFromInterface(string $interface, string $parentProxyClass, array $constructorArguments = null): ?object
    {
        $className = $interface . 'Proxy';
        [$classFileName] = $this->classCache->getClassFileNameAndPath($className, $parentProxyClass);
        $shortClassName = substr($classFileName, 0, strpos($classFileName, '.'));

        if (!($classDeclaration = $this->classCache->get($className, $parentProxyClass))) {
            $classConfig = $this->generateInterfaceProxyClassConfig($this->classConfigurator->getInterfaceConfig($interface), $parentProxyClass);
            $classDeclaration = $this->classRenderer->render($classConfig);
            $this->classCache->set($className, $parentProxyClass, $classDeclaration);
        }
        if ($this->cachePath === null) {
            eval(str_replace('<?php', '', $classDeclaration));
        } else {
            $path = $this->classCache->getClassPath($className, $parentProxyClass);
            require $path;
        }
        return new $shortClassName(...$constructorArguments);
    }

    private function generateInterfaceProxyClassConfig(ClassConfig $interfaceConfig, string $parentProxyClass): ClassConfig
    {
        $interfaceConfig->isInterface = false;
        $interfaceConfig->parent = $parentProxyClass;
        $interfaceConfig->interfaces = [$interfaceConfig->name];
        $interfaceConfig->shortName .= 'Proxy';
        $interfaceConfig->name .= 'Proxy';
        foreach ($interfaceConfig->methods as $methodIndex => $method) {
            foreach ($method->modifiers as $index => $modifier) {
                if ($modifier === 'abstract') {
                    unset($interfaceConfig->methods[$methodIndex]->modifiers[$index]);
                }
            }
        }

        return $interfaceConfig;
    }
}
