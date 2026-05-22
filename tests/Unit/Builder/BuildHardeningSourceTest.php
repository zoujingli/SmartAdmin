<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://doc.hyperf.thinkadmin.top
 */

namespace Tests\Unit\Builder;

use Builder\Ast\Ast;
use Builder\Ast\Visitor\ObfuscateFunctionBodyVisitor;
use Builder\Support\Builder;
use Hyperf\Contract\StdoutLoggerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 */
#[CoversNothing]
final class BuildHardeningSourceTest extends TestCase
{
    public function testBuilderEnablesFirstPartyPhpSourceHardening(): void
    {
        $root = dirname(__DIR__, 3);
        $builderRoot = $this->builderRoot($root);
        $builder = file_get_contents($builderRoot . '/Support/Builder.php');
        $custom = file_get_contents($builderRoot . '/Support/Custom.php');

        self::assertIsString($builder);
        self::assertIsString($custom);

        self::assertStringContainsString('stripPhpSources', $builder);
        self::assertStringContainsString("'app/'", $builder);
        self::assertStringContainsString("'plugin/'", $builder);
        self::assertStringContainsString("'vendor/zoujingli/'", $builder);
        self::assertStringContainsString('xadmin_obfuscate.php', $builder);
        self::assertStringContainsString('getObfuscationRuntimeCode', $builder);
        self::assertStringContainsString('storage/extra/web-dist.zip', $builder);
        self::assertStringContainsString('createFrontendArchive', $builder);
        self::assertStringNotContainsString('Adding web/dist static bundle', $builder);
        self::assertStringNotContainsString('compressFiles(\Phar::GZ)', $builder);
        $runtimeRequirePosition = strpos($builder, 'xadmin_obfuscate.php');
        $mountClosurePosition = strpos($builder, 'array_walk(\$mount');
        self::assertIsInt($runtimeRequirePosition);
        self::assertIsInt($mountClosurePosition);
        self::assertLessThan($mountClosurePosition, $runtimeRequirePosition, '入口挂载闭包会被方法体混淆，运行时解码函数必须先于 array_walk 加载。');

        self::assertStringContainsString('php_strip_whitespace', $custom);
        self::assertStringContainsString('ObfuscateFunctionBodyVisitor', $custom);
        self::assertStringContainsString('private ?Ast $ast = null', $custom);
        self::assertStringContainsString('getPhpStripCacheFile', $custom);
        self::assertStringContainsString('containsRuntimeDocblockAnnotation', $custom);
        self::assertStringContainsString('DocBlock 运行时注解', $custom);
    }

    public function testPhpStripWhitespaceKeepsPhp8Attributes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'xadmin-attribute-');
        self::assertIsString($file);
        file_put_contents($file, "<?php\n/** runtime comments */\n#[DemoAttribute('ok')]\nfinal class DemoClass {}\n");

        try {
            $stripped = php_strip_whitespace($file);
        } finally {
            @unlink($file);
        }

        self::assertStringContainsString("#[DemoAttribute('ok')]", $stripped);
        self::assertStringNotContainsString('runtime comments', $stripped);
    }

    public function testFunctionBodyObfuscationKeepsRuntimeAttributesAndSignatures(): void
    {
        $code = <<<'PHP_CODE'
<?php
namespace Demo;

use Hyperf\HttpServer\Annotation\Controller;

#[Controller]
final class DemoController
{
    public function index(string $name): string
    {
        $message = '设备不存在';
        $total = strlen($message . $name);
        return $message . $total;
    }
}
PHP_CODE;

        $obfuscated = (new Ast())->parse($code, [new ObfuscateFunctionBodyVisitor('demo.php')]);

        self::assertStringContainsString('#[Controller]', $obfuscated);
        self::assertMatchesRegularExpression('/public function index\(string \$name\)\s*:\s*string/', $obfuscated);
        self::assertStringContainsString('\xadmin_obf_s(', $obfuscated);
        self::assertStringNotContainsString('$message', $obfuscated);
        self::assertStringNotContainsString('$total', $obfuscated);
        self::assertStringNotContainsString('设备不存在', $obfuscated);
    }

    public function testFunctionBodyObfuscationDoesNotRewriteAnonymousClassStructure(): void
    {
        $code = <<<'PHP_CODE'
<?php
namespace Demo;

final class DemoFactory
{
    public function make(): object
    {
        $outer = '外层字符串';
        return new class($outer) {
            public string $label = '内部属性默认值';

            public function __construct(public string $name)
            {
            }
        };
    }
}
PHP_CODE;

        $obfuscated = (new Ast())->parse($code, [new ObfuscateFunctionBodyVisitor('anonymous.php')]);

        self::assertStringContainsString('\xadmin_obf_s(', $obfuscated);
        self::assertStringNotContainsString('$outer', $obfuscated);
        self::assertStringNotContainsString('外层字符串', $obfuscated);
        self::assertStringContainsString('public string $label = \'内部属性默认值\';', $obfuscated);
    }

    public function testFunctionBodyObfuscationKeepsStaticLocalVariablesConsistent(): void
    {
        $code = <<<'PHP_CODE'
<?php
namespace Demo;

final class DemoCounter
{
    public function next(): int
    {
        static $counter = 0;
        $counter++;
        return $counter;
    }
}
PHP_CODE;

        $obfuscated = (new Ast())->parse($code, [new ObfuscateFunctionBodyVisitor('static.php')]);

        self::assertStringNotContainsString('static $counter', $obfuscated);
        self::assertStringNotContainsString('return $counter', $obfuscated);
        self::assertMatchesRegularExpression('/static (\$_x[a-f0-9]{10}) = 0;\s*\1\+\+;\s*return \1;/s', $obfuscated);
    }

    public function testFunctionBodyObfuscationEncodesInterpolatedStringParts(): void
    {
        $code = <<<'PHP_CODE'
<?php
namespace Demo;

final class DemoMessage
{
    public function say(string $name): string
    {
        $message = "你好{$name}，欢迎";
        return $message;
    }
}
PHP_CODE;

        $obfuscated = (new Ast())->parse($code, [new ObfuscateFunctionBodyVisitor('interpolated.php')]);

        self::assertStringContainsString('\xadmin_obf_s(', $obfuscated);
        self::assertStringContainsString('$name', $obfuscated);
        self::assertStringNotContainsString('你好', $obfuscated);
        self::assertStringNotContainsString('欢迎', $obfuscated);
    }

    public function testFunctionBodyObfuscationKeepsStringCastForPureInterpolatedVariable(): void
    {
        $code = <<<'PHP_CODE'
<?php
namespace Demo;

final class DemoStringCast
{
    public function normalize(int $item): string
    {
        return trim("{$item}");
    }
}
PHP_CODE;

        $obfuscated = (new Ast())->parse($code, [new ObfuscateFunctionBodyVisitor('string-cast.php')]);

        self::assertStringContainsString('trim((string) $item)', $obfuscated);
        self::assertStringNotContainsString('trim($item)', $obfuscated);
    }

    public function testObfuscationRuntimeDecodesAndCachesStrings(): void
    {
        $logger = new class extends AbstractLogger implements StdoutLoggerInterface {
            public function log($level, string|\Stringable $message, array $context = []): void {}
        };
        $builder = new Builder(dirname(__DIR__, 3) . '/composer.json', $logger);
        $runtime = $builder->getObfuscationRuntimeCode();

        self::assertStringContainsString('static $cache = [], $tables = [];', $runtime);
        self::assertStringContainsString('strtr($binary', $runtime);

        if (!function_exists('xadmin_obf_s')) {
            eval((string)preg_replace('/^<\?php\s*declare\(strict_types=1\);\s*/', '', $runtime));
        }

        $source = '执行效率';
        $key = 137;
        $encoded = '';
        foreach (str_split($source) as $char) {
            $encoded .= chr(ord($char) ^ $key);
        }
        $encoded = strrev(base64_encode($encoded));

        $decoder = \Closure::fromCallable('xadmin_obf_s');
        self::assertSame($source, $decoder($encoded, $key));
        self::assertSame($source, $decoder($encoded, $key));
    }

    public function testBuildPackerUsesUnifiedModesAndRuntimeAudit(): void
    {
        $root = dirname(__DIR__, 3);
        $packer = file_get_contents($root . '/.php-sfx-packer.php');
        $composer = json_decode((string)file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsString($packer);
        self::assertStringContainsString("case 'build'", $packer);
        self::assertStringContainsString("case 'precompile'", $packer);
        self::assertStringContainsString("case 'audit'", $packer);
        self::assertStringContainsString("case 'pack'", $packer);
        self::assertStringContainsString('assertBundledSwooleRuntime', $packer);
        self::assertStringContainsString('requiredRuntimeExtensions', $packer);
        self::assertStringContainsString('storage/extra/web-dist.zip', $packer);
        self::assertStringContainsString('stream_copy_to_stream', $packer);
        self::assertStringContainsString("removePath('build')", $packer);
        self::assertStringNotContainsString('tools/phpsfx', $packer);
        self::assertFileDoesNotExist($root . '/bin/build-precompile');
        self::assertSame('@php .php-sfx-packer.php build', $composer['scripts']['build']);
        self::assertSame('rm -rf system.bin build runtime/container', $composer['scripts']['build:clean']);
    }

    public function testPharBootstrapAutoPublishesFrontendOnlyOnStart(): void
    {
        $root = dirname(__DIR__, 3);
        $bootstrap = file_get_contents($root . '/bin/hyperf.php');
        $publishPath = $root . '/plugin' . '/Library/Command/SitePublish.php';
        if (! is_file($publishPath)) {
            // SmartAdmin 开源仓不携带 Library 源目录，导出测试需回退到 Composer 包路径。
            $publishPath = $root . '/vendor/zoujingli/smart-admin-library/Command/SitePublish.php';
        }
        $publish = file_get_contents($publishPath);

        self::assertIsString($bootstrap);
        self::assertIsString($publish);
        self::assertStringContainsString('FrontendPublisher::publicReady()', $bootstrap);
        self::assertStringContainsString("in_array('start'", $bootstrap);
        self::assertStringContainsString('FrontendPublisher::publish', $bootstrap);
        self::assertStringContainsString('$isPharRuntime ? \'512M\' : \'2G\'', $bootstrap);
        self::assertStringContainsString('opcache_reset', $bootstrap);
        self::assertStringContainsString('FrontendPublisher::clean', $publish);
        self::assertStringContainsString('FrontendPublisher::publish', $publish);
    }

    private function builderRoot(string $root): string
    {
        $sourcePath = $root . '/plugin/Builder';
        if (is_dir($sourcePath)) {
            return $sourcePath;
        }

        // SmartAdmin 开源仓不携带 Builder 源目录，导出测试需回退到 Composer 包路径。
        $vendorPath = $root . '/vendor/zoujingli/smart-admin-builder';
        if (is_dir($vendorPath)) {
            return $vendorPath;
        }

        self::fail('SmartAdminBuilder source path not found.');
    }
}
