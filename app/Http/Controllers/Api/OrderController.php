<?php

namespace App\Http\Controllers\Api; 

use App\Http\Controllers\Controller; 
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function myOrders(Request $request)
    {
        // Fetches only orders for the logged-in customer
        $orders = Order::where('user_id', $request->user()->id)
                       ->with('equipment') 
                       ->latest()
                       ->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'equipment_id' => 'required|exists:equipment,id',
            'total_price' => 'required|numeric'
        ]);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'equipment_id' => $request->equipment_id,
            'quantity' => 1,
            'total_price' => $request->total_price,
            'status' => 'pending'
        ]);

        return response()->json(['status' => 'success', 'message' => 'Order placed!', 'data' => $order]);
    }
}