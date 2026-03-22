<?php

namespace Vinkius\Vurb\Tests\Unit;

use Illuminate\Support\Facades\File;
use Vinkius\Vurb\Attributes\Cached;
use Vinkius\Vurb\Attributes\FsmBind;
use Vinkius\Vurb\Attributes\Invalidates;
use Vinkius\Vurb\Attributes\Presenter;
use Vinkius\Vurb\Attributes\Stale;
use Vinkius\Vurb\Skills\SkillsRegistry;
use Vinkius\Vurb\Tests\TestCase;
use Vinkius\Vurb\Tests\Fixtures\CustomerPresenter;
use Vinkius\Vurb\Tools\Concerns\HasFsmBinding;
use Vinkius\Vurb\Tools\Concerns\HasPresenter;
use Vinkius\Vurb\Tools\Concerns\HasStateSync;

// ─── Stub tools that use the traits ───

#[Cached(ttl: 60)]
#[Invalidates('customers.*', 'reports.*')]
class StubToolWithCached
{
    use HasStateSync;
}

class StubToolWithoutAttrs
{
    use HasStateSync;
    use HasFsmBinding;
    use HasPresenter;
}

#[FsmBind(states: ['payment'], event: 'PAY')]
class StubToolWithFsm
{
    use HasFsmBinding;
}

#[Presenter(resource: CustomerPresenter::class)]
class StubToolWithPresenter
{
    use HasPresenter;
}

#[Stale]
class StubToolWithStale
{
    use HasStateSync;
}

#[Cached]
class StubToolWithCachedNoTtl
{
    use HasStateSync;
}

class ToolsConcernsTest extends TestCase
{
    // ═══ HasStateSync ═══

    public function test_get_state_sync_config_cached_with_ttl(): void
    {
        $tool = new StubToolWithCached();
        $config = $tool->getStateSyncConfig();

        $this->assertSame('stale-after', $config['policy']);
        $this->assertSame(60, $config['ttl']);
    }

    public function test_get_state_sync_config_returns_null_when_no_attribute(): void
    {
        $tool = new StubToolWithoutAttrs();
        $config = $tool->getStateSyncConfig();

        $this->assertNull($config);
    }

    public function test_get_invalidation_patterns(): void
    {
        $tool = new StubToolWithCached();
        $patterns = $tool->getInvalidationPatterns();

        $this->assertSame(['customers.*', 'reports.*'], $patterns);
    }

    public function test_get_invalidation_patterns_empty(): void
    {
        $tool = new StubToolWithoutAttrs();
        $patterns = $tool->getInvalidationPatterns();

        $this->assertEmpty($patterns);
    }

    // ═══ HasFsmBinding ═══

    public function test_get_fsm_binding_returns_states_and_event(): void
    {
        $tool = new StubToolWithFsm();
        $binding = $tool->getFsmBinding();

        $this->assertSame(['payment'], $binding['states']);
        $this->assertSame('PAY', $binding['event']);
    }

    public function test_get_fsm_binding_returns_null_when_no_attribute(): void
    {
        $tool = new StubToolWithoutAttrs();
        $binding = $tool->getFsmBinding();

        $this->assertNull($binding);
    }

    // ═══ HasPresenter ═══

    public function test_get_presenter_class_returns_null_when_no_attribute(): void
    {
        $tool = new StubToolWithoutAttrs();
        $presenterClass = $tool->getPresenterClass();

        $this->assertNull($presenterClass);
    }

    public function test_get_presenter_class_returns_class_when_attribute_present(): void
    {
        $tool = new StubToolWithPresenter();
        $presenterClass = $tool->getPresenterClass();

        $this->assertSame(CustomerPresenter::class, $presenterClass);
    }

    // ═══ HasStateSync — Stale & Cached without TTL ═══

    public function test_get_state_sync_config_stale_returns_stale_policy(): void
    {
        $tool = new StubToolWithStale();
        $config = $tool->getStateSyncConfig();

        $this->assertSame('stale', $config['policy']);
    }

    public function test_get_state_sync_config_cached_without_ttl_returns_cached_policy(): void
    {
        $tool = new StubToolWithCachedNoTtl();
        $config = $tool->getStateSyncConfig();

        $this->assertSame('cached', $config['policy']);
        $this->assertArrayNotHasKey('ttl', $config);
    }

    // ═══ SkillsRegistry ═══

    public function test_skills_registry_register_manual(): void
    {
        $registry = new SkillsRegistry();
        $registry->register('greeting', 'Says hello', '# Greeting\nHello world.');

        $this->assertCount(1, $registry->all());
        $skill = $registry->get('greeting');
        $this->assertSame('greeting', $skill['name']);
        $this->assertSame('Says hello', $skill['description']);
    }

    public function test_skills_registry_get_nonexistent(): void
    {
        $registry = new SkillsRegistry();
        $this->assertNull($registry->get('nonexistent'));
    }

    public function test_skills_registry_compile_all(): void
    {
        $registry = new SkillsRegistry();
        $registry->register('greeting', 'Says hello', '# Greeting');
        $registry->register('farewell', 'Says bye', '# Farewell');

        $compiled = $registry->compileAll();
        $this->assertCount(2, $compiled);
        $this->assertSame('greeting', $compiled[0]['name']);
        $this->assertSame('Says hello', $compiled[0]['description']);
    }

    public function test_skills_registry_discover_nonexistent_dir(): void
    {
        $registry = new SkillsRegistry();
        $result = $registry->discover('/nonexistent/path');

        $this->assertEmpty($result);
    }

    public function test_skills_registry_discover_from_temp_dir(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vurb_skills_test_' . uniqid();
        $skillDir = $tmpDir . '/greeting';
        mkdir($skillDir, 0755, true);
        file_put_contents($skillDir . '/SKILL.md', "# Greeting Skill\nHello world! This is a greeting skill.");

        $registry = new SkillsRegistry();
        $result = $registry->discover($tmpDir);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('greeting', $result);
        $this->assertSame('Hello world! This is a greeting skill.', $result['greeting']['description']);

        // Cleanup
        unlink($skillDir . '/SKILL.md');
        rmdir($skillDir);
        rmdir($tmpDir);
    }

    public function test_skills_registry_extract_description_truncates_long_text(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vurb_skills_test_' . uniqid();
        $skillDir = $tmpDir . '/longskill';
        mkdir($skillDir, 0755, true);
        $longLine = str_repeat('A', 250);
        file_put_contents($skillDir . '/SKILL.md', "# Title\n{$longLine}");

        $registry = new SkillsRegistry();
        $result = $registry->discover($tmpDir);

        $desc = $result['longskill']['description'];
        $this->assertSame(203, mb_strlen($desc)); // 200 + '...'
        $this->assertStringEndsWith('...', $desc);

        // Cleanup
        unlink($skillDir . '/SKILL.md');
        rmdir($skillDir);
        rmdir($tmpDir);
    }

    public function test_skills_registry_extract_description_empty_content(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vurb_skills_test_' . uniqid();
        $skillDir = $tmpDir . '/empty';
        mkdir($skillDir, 0755, true);
        file_put_contents($skillDir . '/SKILL.md', "# Just a heading\n\n\n");

        $registry = new SkillsRegistry();
        $result = $registry->discover($tmpDir);

        $this->assertSame('', $result['empty']['description']);

        // Cleanup
        unlink($skillDir . '/SKILL.md');
        rmdir($skillDir);
        rmdir($tmpDir);
    }
}
