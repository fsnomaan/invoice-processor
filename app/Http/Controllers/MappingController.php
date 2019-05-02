<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CompanyName;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
//        die(var_dump($request->all()));
        if ($request->action == 'save') {
            $this->companyName->name = $request->mapName;
            $this->companyName->map_to = $request->mapTo;
            $this->companyName->user_id = $request->userId;
            $this->companyName->save();

            return Response(
                json_encode(
                    [
                        'message' => 'saved',
                        'name' => $this->companyName->name,
                        'map_to' => $this->companyName->map_to,
                        'id' => DB::getPdo()->lastInsertId(),
                    ]
                ),
                200);
        }


        if ($request->action == 'remove') {
            $this->companyName::where('id', $request->removeId)->delete();
            return Response(
                json_encode(
                    ['message' => 'removed']
                ),
                200);
        }

        exit;
    }

    public function mapBankAccountNumber(Request $request)
    {
        //        die(var_dump($request->all()));
        if ($request->action == 'save') {
            $this->bankAccount->bank_acc_number = $request->mapNumber;
            $this->bankAccount->bank_acc_id = $request->mapTo;
            $this->bankAccount->user_id = $request->userId;
            $this->bankAccount->save();

            return Response(
                json_encode(
                    [
                        'message' => 'saved',
                        'map_number' => $this->bankAccount->bank_acc_number,
                        'map_to' => $this->bankAccount->bank_acc_id,
                        'id' => DB::getPdo()->lastInsertId(),
                    ]
                ),
                200);
        }


        if ($request->action == 'remove') {
            $this->bankAccount::where('id', $request->removeId)->delete();
            return Response(
                json_encode(
                    ['message' => 'removed']
                ),
                200);
        }

        exit;
    }
}
