<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CompanyName;
use App\User;
use Illuminate\Http\Request;

class MappingController extends Controller
{
    /**
     * @var CompanyName
     */
    private $companyName;
    /**
     * @var BankAccount
     */
    private $bankAccount;

    public function __construct(CompanyName $companyName, BankAccount $bankAccount)
    {
        $this->companyName = $companyName;
        $this->bankAccount = $bankAccount;
    }

    public function mapCompanyName(Request $request)
    {
        if ($request->actionName == 'save') {
            $this->companyName->name = $request->mapName;
            $this->companyName->map_to = $request->mapTo;
            $this->companyName->user_id = $request->userId;
            $this->companyName->save();
            session()->put('notifications','Company name created successfully.');
            return redirect()->action(
                'ProcessInvoiceController@index', ['userName' => User::find($request->userId)->name]
            );
        }
        $parts = explode('=>',$request->actionName);
        if ($parts[0]  == 'remove') {
            $this->companyName::where('name', $parts[1])->delete();
            session()->put('notifications','Successfully deleted');
            return redirect()->action(
                'ProcessInvoiceController@index', ['userName' => User::find($request->userId)->name]
            );
        }
    }

    public function mapBankAccountNumber(Request $request)
    {
        if ($request->actionName == 'save') {
            $this->bankAccount->bank_acc_number = $request->mapNumber;
            $this->bankAccount->bank_acc_id = $request->mapTo;
            $this->bankAccount->user_id = $request->userId;
            $this->bankAccount->save();
            session()->put('notifications','Account map created successfully.');
            return redirect()->action(
                'ProcessInvoiceController@index', ['userName' => User::find($request->userId)->name]
            );
        }
        $parts = explode('=>',$request->actionName);
        if ($parts[0]  == 'remove') {
            $this->bankAccount::where('bank_acc_number', $parts[1])->delete();
            session()->put('notifications','Successfully deleted');
            return redirect()->action(
                'ProcessInvoiceController@index', ['userName' => User::find($request->userId)->name]
            );
        }
    }
}
