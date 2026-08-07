<?php

namespace Tests\Feature\Vendor;

use App\Http\Controllers\Vendor\VendorCatalogController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class VendorCatalogOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The PDO SQLite driver is not installed.');
        }

        config([
            'database.default' => 'vendor_catalog_order_test',
            'database.connections.vendor_catalog_order_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('vendor_catalog_order_test');
        DB::setDefaultConnection('vendor_catalog_order_test');

        Schema::create('Products_Departments_T', function (Blueprint $table): void {
            $table->id();
            $table->string('Product_Department_Name');
            $table->string('Product_Department_Name_Ar')->nullable();
        });
        Schema::create('Products_Sub_Department_T', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('Products_Departments_Id');
            $table->string('Sub_Department_Name');
            $table->string('Sub_Department_Name_Ar')->nullable();
        });
        Schema::create('Products_Sub_Sub_Department_T', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('Product_Sub_Department_Id');
            $table->string('Product_Sub_Sub_Department_Name');
            $table->string('Product_Sub_Sub_Department_Name_Ar')->nullable();
        });
    }

    protected function tearDown(): void
    {
        if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            Schema::dropIfExists('Products_Sub_Sub_Department_T');
            Schema::dropIfExists('Products_Sub_Department_T');
            Schema::dropIfExists('Products_Departments_T');
            DB::purge('vendor_catalog_order_test');
        }

        parent::tearDown();
    }

    public function test_catalog_hierarchy_endpoints_honor_display_order(): void
    {
        $this->addSourceSequenceColumns();
        $this->addDisplayOrderColumns();
        $this->seedHierarchy(true, true);

        $controller = new VendorCatalogController;

        $this->assertSame([2, 1], $controller->departments()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([12, 11], $controller->subDepartments(1)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([22, 21], $controller->subSubDepartments(11)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_catalog_hierarchy_endpoints_use_source_order_before_display_order_is_deployed(): void
    {
        $this->addSourceSequenceColumns();
        $this->seedHierarchy(false, true);

        $controller = new VendorCatalogController;

        $this->assertSame([1, 2], $controller->departments()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([11, 12], $controller->subDepartments(1)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([21, 22], $controller->subSubDepartments(11)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_catalog_hierarchy_endpoints_use_name_and_id_on_the_legacy_schema(): void
    {
        $this->seedHierarchy(false, false);

        $controller = new VendorCatalogController;

        $this->assertSame([2, 1], $controller->departments()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([12, 11], $controller->subDepartments(1)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([22, 21], $controller->subSubDepartments(11)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    private function addSourceSequenceColumns(): void
    {
        Schema::table('Products_Departments_T', fn (Blueprint $table) => $table->unsignedInteger('Source_Main_Sequence')->nullable());
        Schema::table('Products_Sub_Department_T', fn (Blueprint $table) => $table->unsignedInteger('Source_Sub_Sequence')->nullable());
        Schema::table('Products_Sub_Sub_Department_T', fn (Blueprint $table) => $table->unsignedInteger('Source_Sub_Sub_Sequence')->nullable());
    }

    private function addDisplayOrderColumns(): void
    {
        Schema::table('Products_Departments_T', fn (Blueprint $table) => $table->bigInteger('Display_Order')->nullable());
        Schema::table('Products_Sub_Department_T', fn (Blueprint $table) => $table->bigInteger('Display_Order')->nullable());
        Schema::table('Products_Sub_Sub_Department_T', fn (Blueprint $table) => $table->bigInteger('Display_Order')->nullable());
    }

    private function seedHierarchy(bool $withDisplayOrder, bool $withSourceSequence): void
    {
        DB::table('Products_Departments_T')->insert([
            [
                'id' => 1,
                'Product_Department_Name' => 'Zulu',
                ...($withSourceSequence ? ['Source_Main_Sequence' => 1] : []),
                ...($withDisplayOrder ? ['Display_Order' => 20] : []),
            ],
            [
                'id' => 2,
                'Product_Department_Name' => 'Alpha',
                ...($withSourceSequence ? ['Source_Main_Sequence' => 2] : []),
                ...($withDisplayOrder ? ['Display_Order' => 10] : []),
            ],
        ]);
        DB::table('Products_Sub_Department_T')->insert([
            [
                'id' => 11,
                'Products_Departments_Id' => 1,
                'Sub_Department_Name' => 'Zulu',
                ...($withSourceSequence ? ['Source_Sub_Sequence' => 1] : []),
                ...($withDisplayOrder ? ['Display_Order' => 20] : []),
            ],
            [
                'id' => 12,
                'Products_Departments_Id' => 1,
                'Sub_Department_Name' => 'Alpha',
                ...($withSourceSequence ? ['Source_Sub_Sequence' => 2] : []),
                ...($withDisplayOrder ? ['Display_Order' => 10] : []),
            ],
        ]);
        DB::table('Products_Sub_Sub_Department_T')->insert([
            [
                'id' => 21,
                'Product_Sub_Department_Id' => 11,
                'Product_Sub_Sub_Department_Name' => 'Zulu',
                ...($withSourceSequence ? ['Source_Sub_Sub_Sequence' => 1] : []),
                ...($withDisplayOrder ? ['Display_Order' => 20] : []),
            ],
            [
                'id' => 22,
                'Product_Sub_Department_Id' => 11,
                'Product_Sub_Sub_Department_Name' => 'Alpha',
                ...($withSourceSequence ? ['Source_Sub_Sub_Sequence' => 2] : []),
                ...($withDisplayOrder ? ['Display_Order' => 10] : []),
            ],
        ]);
    }
}
