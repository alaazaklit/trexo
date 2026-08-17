<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\User;
use App\Services\Firebase\FcmMessagingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class BroadcastExcelTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // FcmMessagingService's constructor talks to Google for a real
        // access token — swap in a mock so these tests never need live
        // Firebase credentials and never make network calls.
        $fcm = Mockery::mock(FcmMessagingService::class);
        $fcm->shouldReceive('sendNotification')->zeroOrMoreTimes()->andReturn(response()->json(['results' => []]));
        $this->app->instance(FcmMessagingService::class, $fcm);

        Permission::firstOrCreate(['name' => 'broadcasts.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'broadcasts.manage', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['phone' => 'admin-'.uniqid()]);
        $this->admin->givePermissionTo(['broadcasts.view', 'broadcasts.manage']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Builds a real, minimal .xlsx file on disk (no PhpSpreadsheet needed to write one) and wraps it as an UploadedFile. */
    private function makeExcelUpload(array $rows, string $filename = 'recipients.xlsx'): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('broadcast_test_', true).'.xlsx';
        $this->writeMinimalXlsx($path, array_merge([['phone']], $rows));

        return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Hand-rolls the smallest valid .xlsx (a zip of a few OOXML parts) with
     * one sheet, using inline strings so no shared-strings table is needed.
     */
    private function writeMinimalXlsx(string $path, array $rows): void
    {
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $i => $row) {
            $rowNum = $i + 1;
            $sheetXml .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $j => $value) {
                $cellRef = chr(65 + $j).$rowNum;
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $sheetXml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>';
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
    }

    public function test_filtration_broadcast_is_unaffected_by_the_excel_changes(): void
    {
        $driver = User::factory()->create([
            'phone' => '71111111',
            'type' => 'driver',
            'account_status' => 'active',
            'fcm_token' => 'token-driver',
        ]);
        User::factory()->create([
            'phone' => '71111112',
            'type' => 'seller',
            'account_status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.store'), [
            'title' => 'Hello',
            'message' => 'Filtration still works',
            'account_type' => 'driver',
        ]);

        $response->assertRedirect(route('admin.broadcasts.index'));

        $broadcast = Broadcast::latest('id')->first();
        $this->assertSame(1, $broadcast->recipient_count);
        $this->assertSame(Broadcast::SOURCE_FILTRATION, $broadcast->source);
        $this->assertNull($broadcast->source_file_name);
        $this->assertDatabaseHas('notifications', ['user_id' => $driver->id, 'section' => 'broadcast']);
    }

    public function test_excel_preview_rejects_a_file_missing_the_phone_column(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('broadcast_test_', true).'.xlsx';
        $this->writeMinimalXlsx($path, [['name'], ['Someone']]);
        $file = new UploadedFile($path, 'no_phone.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.preview'), [
            'title' => 'Hi',
            'message' => 'Test',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertNull(session('broadcast.excel_pending'));
    }

    public function test_excel_preview_rejects_a_non_excel_file_with_a_spoofed_extension(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('broadcast_test_', true).'.xlsx';
        file_put_contents($path, 'this is not really an excel file, just plain text');
        $file = new UploadedFile($path, 'fake.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.preview'), [
            'title' => 'Hi',
            'message' => 'Test',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_excel_preview_validates_normalizes_dedupes_and_matches_recipients(): void
    {
        $registeredA = User::factory()->create([
            'phone' => '71234567',
            'type' => 'driver',
            'account_status' => 'active',
            'fcm_token' => 'token-a',
        ]);
        $registeredB = User::factory()->create([
            'phone' => '70123456',
            'type' => 'seller',
            'account_status' => 'active',
            'fcm_token' => null,
        ]);

        $file = $this->makeExcelUpload([
            ['96171234567'],   // -> registered A
            ['70123456'],      // -> registered B
            ['+961 71 234 567'], // duplicate of row 1 after normalizing
            ['12'],            // invalid (too short)
            [''],              // empty, ignored
            ['99999999'],      // valid format, no matching user
            [null],            // empty, ignored
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.preview'), [
            'title' => 'Promo',
            'message' => 'Big sale',
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.broadcasts.index'));
        $pending = session('broadcast.excel_pending');
        $this->assertNotNull($pending);

        $summary = $pending['summary'];
        $this->assertSame(7, $summary['total_rows']);
        $this->assertSame(2, $summary['empty_rows_ignored']);
        $this->assertSame(1, $summary['invalid_count']);
        $this->assertSame(1, $summary['duplicate_count']);
        $this->assertSame(1, $summary['not_found_count']);
        $this->assertSame(['99999999'], $summary['not_found_numbers']);
        $this->assertSame(2, $summary['final_count']);

        $recipientIds = collect($pending['recipients'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$registeredA->id, $registeredB->id], $recipientIds);
    }

    public function test_excel_preview_with_no_valid_recipients_is_rejected(): void
    {
        $file = $this->makeExcelUpload([
            ['12'],
            [''],
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.preview'), [
            'title' => 'Promo',
            'message' => 'Big sale',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertNull(session('broadcast.excel_pending'));
    }

    public function test_excel_send_without_a_prior_preview_is_rejected(): void
    {
        // Compares against a before-count rather than asserting an absolute
        // 0 — the `broadcasts` table is MyISAM on this server (this
        // project's MySQL default, not something this test controls), which
        // doesn't participate in DatabaseTransactions' rollback, so rows
        // from earlier tests in the same run are still visible here.
        $before = Broadcast::count();

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.send'));

        $response->assertSessionHasErrors('file');
        $this->assertSame($before, Broadcast::count());
    }

    public function test_excel_send_after_confirm_uses_the_same_pipeline_and_records_history(): void
    {
        $registered = User::factory()->create([
            'phone' => '71234567',
            'type' => 'driver',
            'account_status' => 'active',
            'fcm_token' => 'token-a',
        ]);

        $file = $this->makeExcelUpload([
            ['96171234567'],
        ], 'my_recipients.xlsx');

        $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.preview'), [
            'title' => 'Promo',
            'message' => 'Big sale',
            'file' => $file,
        ]);

        $this->assertNotNull(session('broadcast.excel_pending'));

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.send'));
        $response->assertRedirect(route('admin.broadcasts.index'));

        $this->assertNull(session('broadcast.excel_pending'));

        $broadcast = Broadcast::latest('id')->first();
        $this->assertSame(Broadcast::SOURCE_EXCEL, $broadcast->source);
        $this->assertSame('my_recipients.xlsx', $broadcast->source_file_name);
        $this->assertSame(1, $broadcast->recipient_count);
        $this->assertDatabaseHas('notifications', ['user_id' => $registered->id, 'section' => 'broadcast']);

        $historyResponse = $this->actingAs($this->admin)->get(route('admin.broadcasts.index'));
        $historyResponse->assertSee('Excel');
        $historyResponse->assertSee('my_recipients.xlsx');
    }

    public function test_excel_preview_handles_a_larger_number_of_rows(): void
    {
        $rows = [];
        $expectedPhones = [];
        for ($i = 0; $i < 60; $i++) {
            $phone = '70'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT);
            $rows[] = ['961'.$phone];
            $expectedPhones[] = $phone;
            User::factory()->create([
                'phone' => $phone,
                'type' => 'driver',
                'account_status' => 'active',
            ]);
        }

        $file = $this->makeExcelUpload($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.broadcasts.excel.preview'), [
            'title' => 'Promo',
            'message' => 'Big sale',
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.broadcasts.index'));
        $summary = session('broadcast.excel_pending')['summary'];
        $this->assertSame(60, $summary['total_rows']);
        $this->assertSame(60, $summary['final_count']);
    }
}
