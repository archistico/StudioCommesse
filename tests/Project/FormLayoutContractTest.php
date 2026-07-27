<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormLayoutContractTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function formProvider(): iterable
    {
        yield 'allegato' => ['AttachmentUploadType.php', 'attachment/project.html.twig', ['file', 'description']];
        yield 'metadati allegato' => ['AttachmentMetadataType.php', 'attachment/show.html.twig', ['description']];
        yield 'attività' => ['ActivityType.php', 'activity/form.html.twig', ['description']];
        yield 'cliente' => ['ClientType.php', 'client/form.html.twig', ['address', 'notes']];
        yield 'spesa' => ['ExpenseType.php', 'economics/form.html.twig', ['description', 'reimbursable']];
        yield 'incasso' => ['PaymentType.php', 'economics/form.html.twig', ['description', 'notes']];
        yield 'commessa' => ['ProjectType.php', 'project/form.html.twig', ['name', 'description', 'waitingReason', 'privateNote']];
        yield 'ore' => ['TimeEntryType.php', 'time_entry/form.html.twig', ['description', 'billable']];
        yield 'utente' => ['UserType.php', 'user/form.html.twig', []];
    }

    /** @param list<string> $fullWidthFields */
    #[DataProvider('formProvider')]
    public function testEveryConfiguredFieldIsRenderedExplicitlyBeforeTheFullWidthSubmitButton(
        string $formTypeFile,
        string $templateFile,
        array $fullWidthFields,
    ): void {
        $root = dirname(__DIR__, 2);
        $formType = file_get_contents($root.'/src/Form/'.$formTypeFile);
        $template = file_get_contents($root.'/templates/'.$templateFile);

        self::assertIsString($formType);
        self::assertIsString($template);
        self::assertDoesNotMatchRegularExpression('/form_widget\(\s*form\s*\)/', $template);
        self::assertStringContainsString('form_rest(form)', $template);
        self::assertStringContainsString('form_end(form, {render_rest: false})', $template);
        self::assertMatchesRegularExpression('/<button[^>]*class="[^"]*btn-primary[^"]*w-100[^"]*"[^>]*type="submit"|<button[^>]*type="submit"[^>]*class="[^"]*btn-primary[^"]*w-100[^"]*"/', $template);

        preg_match_all("/->add\(\s*'([A-Za-z][A-Za-z0-9]*)'/", $formType, $matches);
        $configuredFields = array_values(array_unique($matches[1] ?? []));
        self::assertNotEmpty($configuredFields, 'Nessun campo individuato in '.$formTypeFile);

        $submitPosition = strpos($template, 'type="submit"');
        $restPosition = strpos($template, 'form_rest(form)');
        self::assertIsInt($submitPosition);
        self::assertIsInt($restPosition);
        self::assertLessThan($submitPosition, $restPosition, 'form_rest deve precedere il pulsante in '.$templateFile);

        foreach ($configuredFields as $field) {
            $needle = 'form_row(form.'.$field.')';
            $fieldPosition = strpos($template, $needle);
            self::assertIsInt($fieldPosition, sprintf('Il campo %s di %s non è renderizzato esplicitamente in %s.', $field, $formTypeFile, $templateFile));
            self::assertLessThan($submitPosition, $fieldPosition, sprintf('Il campo %s compare dopo il pulsante in %s.', $field, $templateFile));
        }

        foreach ($fullWidthFields as $field) {
            self::assertMatchesRegularExpression(
                '/class="col-12"[^>]*>\s*{{\s*form_row\(form\.'.preg_quote($field, '/').'\)\s*}}/',
                $template,
                sprintf('Il campo esteso %s deve occupare una riga intera in %s.', $field, $templateFile),
            );
        }
    }

    /** @return iterable<string, array{string}> */
    public static function responsiveTemplateProvider(): iterable
    {
        foreach ([
            'attachment/project.html.twig',
            'attachment/show.html.twig',
            'activity/form.html.twig',
            'client/form.html.twig',
            'economics/form.html.twig',
            'project/form.html.twig',
            'time_entry/form.html.twig',
            'user/form.html.twig',
        ] as $template) {
            yield $template => [$template];
        }
    }

    #[DataProvider('responsiveTemplateProvider')]
    public function testCompactFieldsSplitOnlyOnLargeScreens(string $templateFile): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/'.$templateFile);

        self::assertIsString($template);
        self::assertStringContainsString('col-12 col-lg-6', $template);
        self::assertStringNotContainsString('col-md-', $template);
    }
}
