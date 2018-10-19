<?php

namespace App\Http\Controllers;

use App\Models\CompanyName;
use Illuminate\Http\Request;

class CompanyNameController extends Controller
{
    /**
     * @var CompanyName
     */
    private $companyName;

    public function __construct(CompanyName $companyName)
    {
        $this->companyName = $companyName;
    }

    public function updateMap(Request $request)
    {
        if ($request->actionName == 'save') {
            $this->companyName->name = $request->mapName;
            $this->companyName->map_to = $request->mapTo;
            $this->companyName->save();
            session()->put('notifications','Item created successfully.');
            return redirect()->action(
                'ProcessInvoiceController@index'
            );
        }
        $parts = explode('=>',$request->actionName);
        if ($parts[0]  == 'remove') {
            $this->companyName::where('name', $parts[1])->delete();
            session()->put('notifications','Successfully deleted');
            return redirect()->action(
                'ProcessInvoiceController@index'
            );
        }
    }
}
