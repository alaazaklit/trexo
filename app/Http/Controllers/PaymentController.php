<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::all();
        return response()->json($payments);
    }

    public function show($id)
    {
        $payment = Payment::findOrFail($id);
        return response()->json($payment);
    }

    public function store(Request $request)
    {
        $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'payment_status' => 'required|string|in:pending,completed,failed'
        ]);

        $payment = Payment::create($request->all());
        return response()->json($payment, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'trip_id' => 'sometimes|required|exists:trips,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'payment_method' => 'sometimes|required|string',
            'payment_status' => 'sometimes|required|string|in:pending,completed,failed'
        ]);

        $payment = Payment::findOrFail($id);
        $payment->update($request->all());
        return response()->json($payment);
    }

    public function destroy($id)
    {
        Payment::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
