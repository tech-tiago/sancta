<?php

namespace Tests\Feature\Admin;

use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** O envio temporário do Livewire precisa aceitar o maior arquivo que algum campo do painel permite. */
class UploadLimitTest extends TestCase
{
    public function test_livewire_accepts_the_largest_panel_upload(): void
    {
        $livewireMax = null;
        foreach (FileUploadConfiguration::rules() as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                $livewireMax = (int) substr($rule, 4);
            }
        }

        $panelMax = 0;
        foreach (Finder::create()->files()->in(app_path('Filament'))->name('*.php') as $file) {
            preg_match_all('/->maxSize\((\d+)\)/', $file->getContents(), $matches);
            foreach ($matches[1] as $size) {
                $panelMax = max($panelMax, (int) $size);
            }
        }

        $this->assertSame(51200, $panelMax, 'Maior limite do painel mudou: ajuste config/livewire.php e o deploy (Nginx/PHP).');
        $this->assertNotNull($livewireMax);
        $this->assertGreaterThanOrEqual($panelMax, $livewireMax);
    }
}
