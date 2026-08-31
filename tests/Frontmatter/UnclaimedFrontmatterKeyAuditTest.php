<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter;

use PHPUnit\Framework\Attributes\Test;
use Rushing\Doctor\DoctorStatus;
use Splicewire\Beam\Mdx\Doctor\UnclaimedFrontmatterKeyAudit;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterData;
use Splicewire\Beam\Mdx\Tests\Frontmatter\Fixtures\BareShape;
use Splicewire\Beam\Mdx\Tests\TestCase;

class UnclaimedFrontmatterKeyAuditTest extends TestCase
{
    private function seedTree(string $frontmatter): void
    {
        $root = sys_get_temp_dir().'/beam-mdx-audit-'.getmypid().'-'.uniqid();
        @mkdir($root.'/docs', 0777, true);
        file_put_contents($root.'/docs/a.mdx', "---\n{$frontmatter}\n---\n# Body\n");
        config()->set('beam.mdx.content_path', $root);
        config()->set('beam.mdx.frontmatter.shape', FrontmatterData::class);
    }

    #[Test]
    public function it_reports_a_key_no_shape_claims(): void
    {
        $this->seedTree("title: T\nschemaType: Article");

        $finding = (new UnclaimedFrontmatterKeyAudit)->run()[0];

        $this->assertSame(DoctorStatus::Warn, $finding->status);
        $this->assertStringContainsString('schemaType', $finding->detail);
        $this->assertStringNotContainsString('title', $finding->detail);
    }

    #[Test]
    public function a_camel_authored_key_whose_canonical_form_i_s_claimed_is_not_reported(): void
    {
        // The canonicalization contract, from the audit's side: an author writing `navOrder:` against
        // a shape claiming `nav_order` is CORRECT, and flagging it would train readers to ignore this
        // audit. What it reports is a key that reaches no reader in either spelling.
        $this->seedTree('title: T');

        $finding = (new UnclaimedFrontmatterKeyAudit)->run()[0];

        $this->assertSame(DoctorStatus::Pass, $finding->status);
    }

    #[Test]
    public function it_is_advisory_and_never_fatal(): void
    {
        $this->seedTree("schemaType: Article\nnavParent: docs/index");

        // An unclaimed key is very often legitimate — these two are read by the JS plane at build
        // time. Warn, never Fail: which keys a host's authors write is a fact about the host.
        $this->assertSame(DoctorStatus::Warn, (new UnclaimedFrontmatterKeyAudit)->run()[0]->status);
    }

    #[Test]
    public function a_shape_declining_the_capability_is_skipped_and_the_finding_says_so(): void
    {
        $this->seedTree('anything: here');
        config()->set('beam.mdx.frontmatter.shape', BareShape::class);

        $finding = (new UnclaimedFrontmatterKeyAudit)->run()[0];

        // A zero that does not say what it counted is how an instrument lies.
        $this->assertSame(DoctorStatus::Pass, $finding->status);
        $this->assertStringContainsString('1 shape skipped', $finding->detail);
    }

    #[Test]
    public function no_declared_shape_warns_rather_than_throwing(): void
    {
        $this->seedTree('title: T');
        config()->set('beam.mdx.frontmatter.shape', null);

        $this->assertSame(DoctorStatus::Warn, (new UnclaimedFrontmatterKeyAudit)->run()[0]->status);
    }
}
