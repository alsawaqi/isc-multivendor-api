<?php

namespace Tests\Unit;

use App\Http\Controllers\Vendor\VendorProductsController;
use App\Http\Controllers\Vendor\VendorStockController;
use App\Models\ProductVendorOffer;
use App\Models\ProductVendorRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorOfferIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'offers_test', 'database.connections.offers_test' => ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        DB::purge('offers_test'); DB::setDefaultConnection('offers_test');
        foreach ([
            'Products_Master_T'=>'Product_Name Vendor_Id Product_Price Product_Cost Minimum_Selling_Price Product_Stock Is_Active Status',
            'Products_Vendor_Offers_T'=>'Products_Id Vendor_Id Product_Price Product_Cost Minimum_Selling_Price Product_Stock Is_Active Status Commission_Type Commission_Value',
            'Products_Vendor_Offer_Bulk_Prices_T'=>'Vendor_Offer_Id Min_Qty Max_Qty Unit_Price',
            'Vendors_Master_T'=>'Vendor_Name Is_Active',
            'Products_Images_T'=>'Products_Id Image_Path',
            'Products_Vendor_Requests_T'=>'Products_Id Products_Temporary_Id Vendor_Id Vendor_Offer_Id Request_Type Status Comment Requested_Changes_Json Action_By_User_Id Action_By_Role Action_At',
            'Product_Stock_Movements_T'=>'Products_Id Vendor_Id Movement_Type Quantity_Delta Quantity Previous_Stock New_Stock Actor_Type Actor_Id Actor_Name Notes',
        ] as $name=>$columns) {
            Schema::create($name,function(Blueprint $t)use($columns){$t->id();foreach(explode(' ',$columns)as$c){$t->text($c)->nullable();}$t->timestamps();$t->softDeletes();});
        }
        DB::table('Vendors_Master_T')->insert([['id'=>1,'Vendor_Name'=>'Seller A'],['id'=>2,'Vendor_Name'=>'Seller B']]);
        DB::table('Products_Master_T')->insert(['id'=>1,'Product_Name'=>'Drill','Vendor_Id'=>1,'Product_Price'=>10.123,'Product_Stock'=>7,'Is_Active'=>1,'Status'=>'available']);
        foreach([1,2]as$id)ProductVendorOffer::create(['Products_Id'=>1,'Vendor_Id'=>$id,'Product_Price'=>$id*10.123,'Product_Stock'=>$id*7,'Is_Active'=>1,'Status'=>'available']);
        Auth::shouldReceive('guard')->with('vendor')->andReturn(new class { public function user(){return(object)['id'=>20,'Vendor_Id'=>2,'name'=>'Seller B'];} });
    }

    protected function tearDown(): void { DB::purge('offers_test');parent::tearDown(); }

    public function test_vendor_updates_its_offer_even_when_master_belongs_to_another_vendor(): void
    {
        $response=(new VendorProductsController)->requestUpdate(Request::create('/vendor/api/products/approved/1/request-update','POST',[
            'changes'=>['Product_Price'=>23.456,'Product_Stock'=>10,'Status'=>'available','bulk_prices'=>[]],
        ]),1);
        $this->assertLessThan(300,$response->getStatusCode(),$response->getContent());
        $request=ProductVendorRequest::firstOrFail();
        $this->assertEquals(2,$request->Vendor_Id);$this->assertEquals(2,$request->Vendor_Offer_Id);
        $this->assertSame('requested',$request->Status);
        $this->assertEquals(23.456,$request->Requested_Changes_Json['Product_Price']);
        $this->assertEquals(20.246,ProductVendorOffer::find(2)->Product_Price,'Price waits for admin approval');
        $this->assertEquals(10.123,DB::table('Products_Master_T')->value('Product_Price'));
    }

    public function test_vendor_cannot_request_shared_product_changes(): void
    {
        $this->expectException(ValidationException::class);
        (new VendorProductsController)->requestUpdate(Request::create('/','POST',['changes'=>['Product_Name'=>'Overwrite catalogue']]),1);
    }

    public function test_vendor_cannot_change_an_unlinked_product(): void
    {
        ProductVendorOffer::find(2)->delete();
        $this->expectException(ModelNotFoundException::class);
        (new VendorProductsController)->requestUpdate(Request::create('/','POST',['changes'=>['Product_Price'=>1]]),1);
    }

    public function test_stock_adjustment_targets_only_the_authenticated_vendor_offer(): void
    {
        $response=(new VendorStockController)->adjust(Request::create('/','POST',['movement_type'=>'increase','quantity'=>3]),1);
        $this->assertSame(200,$response->getStatusCode());
        $this->assertSame(17,ProductVendorOffer::find(2)->Product_Stock);
        $this->assertSame(7,ProductVendorOffer::find(1)->Product_Stock);
        $this->assertEquals(7,DB::table('Products_Master_T')->value('Product_Stock'));
        $this->assertEquals(2,DB::table('Product_Stock_Movements_T')->value('Vendor_Id'));
        $this->assertEquals(17,$response->getData(true)['data']['product']['Product_Stock']);
    }
}
