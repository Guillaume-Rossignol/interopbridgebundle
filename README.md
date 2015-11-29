# InteropBridgeBundle

Import `container-interop` libraries in symfony container

## Usage

Add InteropBridgeBundle in your kernel.

Set the `DefitionsProvider` in the parameter `interop.definitions.providers` :

```yml
parameters:
  interop.definitions.providers: 
    - \GlideModule\GlideDefinitionProvider #with mnapoli/glide-module
```

Now, you can do : `$container->get('glide')` 