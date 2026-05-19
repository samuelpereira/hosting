<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionDocumentController extends Controller
{
    public function __invoke(Transaction $transaction): StreamedResponse
    {
        abort_unless($transaction->hasDocument(), 404);

        return Storage::disk('local')->download(
            $transaction->document_path,
            $transaction->document_name ?? basename($transaction->document_path),
        );
    }
}
