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
            $response = [
                'companyNames' => $this->companyName->getNames(),
                'success' => 'Successfully saved'
            ];
        }
        $parts = explode('=>',$request->actionName);
        if ($parts[0]  == 'remove') {
            $this->companyName::where('name', $parts[1])->delete();
            $response = [
                'companyNames' => $this->companyName->getNames(),
                'success' => 'Successfully deleted '. $parts[1]
            ];
        }
        return view('process_invoice')->with($response);
    }
}
