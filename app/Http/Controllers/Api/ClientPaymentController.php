<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientPaymentController extends Controller
{
    public function index(Client $client)
    {
        $payments = $client->payments()
            ->with('createdBy:id,name')
            ->orderBy('paid_at', 'desc')
            ->paginate(15);

        return response()->json($payments);
    }

    public function store(Client $client, Request $request)
    {
        $validated = $request->validate([
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'paid_at'  => ['required', 'date'],
            'message'  => ['nullable', 'string', 'max:1000'],
            'proof'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $file      = $request->file('proof');
            $dir       = public_path("uploads/client-payments/{$client->short_id}");
            $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $file->move($dir, $filename);
            $proofPath = "uploads/client-payments/{$client->short_id}/{$filename}";
        }

        $payment = $client->payments()->create([
            'amount'     => $validated['amount'],
            'paid_at'    => $validated['paid_at'],
            'message'    => $validated['message'] ?? null,
            'proof_path' => $proofPath,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($payment->load('createdBy:id,name'), 201);
    }

    public function destroy(Client $client, ClientPayment $payment)
    {
        if ($payment->client_id !== $client->id) {
            abort(404);
        }

        if ($payment->proof_path) {
            $fullPath = public_path($payment->proof_path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }
}
