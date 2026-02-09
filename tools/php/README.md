# Ferramentas PHP (qualidade)

## Instalação

```bash
cd tools/php && composer install
```

## Execução

```bash
composer run ci
```

Ou individualmente:

```bash
composer run phpcs
composer run phpstan
composer run psalm
```

## Observação
Se PHPStan/Psalm acusarem muitos problemas, gerar baseline na etapa 3/3.
