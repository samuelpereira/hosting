<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->where('is_public', true)
            ->with(['prices' => fn ($q) => $q->where('is_active', true)->orderBy('amount_minor')])
            ->orderedForDisplay()
            ->get();

        return view('welcome', [
            'products' => $products,
        ]);
    }
}
