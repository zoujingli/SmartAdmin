<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\TranslatorInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

ApplicationContext::setContainer(new class implements ContainerInterface {
    private TranslatorInterface $translator;

    private ConfigInterface $config;

    public function __construct()
    {
        $this->translator = new class implements TranslatorInterface {
            private string $locale = 'zh_CN';

            public function trans(string $key, array $replace = [], ?string $locale = null): array|string
            {
                return strtr($key, $replace);
            }

            public function transChoice(string $key, $number, array $replace = [], ?string $locale = null): string
            {
                return (string)$this->trans($key, $replace, $locale);
            }

            public function getLocale(): string
            {
                return $this->locale;
            }

            public function setLocale(string $locale)
            {
                $this->locale = $locale;
            }
        };
        $this->config = new class implements ConfigInterface {
            public function get($key, $default = null): mixed
            {
                return $key === 'jwt.secret' ? 'unit-test-wechat-secret-key' : $default;
            }

            public function set($key, $value): void {}

            public function has($key): bool
            {
                return $key === 'jwt.secret';
            }
        };
    }

    public function get(string $id)
    {
        if ($id === TranslatorInterface::class) {
            return $this->translator;
        }
        if ($id === ConfigInterface::class) {
            return $this->config;
        }

        throw new class (sprintf('Service "%s" not found.', $id)) extends RuntimeException implements NotFoundExceptionInterface {};
    }

    public function has(string $id): bool
    {
        return in_array($id, [TranslatorInterface::class, ConfigInterface::class], true);
    }
});
