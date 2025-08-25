<?php

namespace App\Http\Controllers;

use App\Models\Gifts;
use App\Models\UserGiftInventory;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GiftInventoryController extends Controller
{
    /**
     * Send gift to user's inventory (replaces direct coin transfer)
     */
    public function sendGiftToUser(Request $request)
    {
        $rules = [
            'recipient_user_id' => 'required|integer|exists:users,id',
            'gift_id' => 'required|integer|exists:gifts,id',
            'sender_user_id' => 'required|integer|exists:users,id',
            'quantity' => 'integer|min:1|max:10'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $quantity = $request->quantity ?? 1;
        $giftId = $request->gift_id;
        $senderId = $request->sender_user_id;
        $recipientId = $request->recipient_user_id;

        // Get gift details
        $gift = Gifts::where('id', $giftId)->where('is_active', true)->first();
        if (!$gift) {
            return response()->json([
                'status' => false,
                'message' => 'Gift not found or inactive'
            ]);
        }

        // Get sender details
        $sender = Users::find($senderId);
        if (!$sender) {
            return response()->json([
                'status' => false,
                'message' => 'Sender not found'
            ]);
        }

        // Check if sender has enough coins
        $totalCost = $gift->coin_price * $quantity;
        if (($sender->wallet ?? 0) < $totalCost) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient coins to send gift'
            ]);
        }

        DB::beginTransaction();
        try {
            // Deduct coins from sender
            $sender->wallet = ($sender->wallet ?? 0) - $totalCost;
            $sender->save();

            // Add gift to recipient's inventory
            $inventoryItem = UserGiftInventory::addGift(
                $recipientId, 
                $giftId, 
                $senderId, 
                $quantity
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Gift sent successfully',
                'data' => [
                    'inventory_item' => $inventoryItem->load(['gift', 'sender']),
                    'sender_remaining_wallet' => $sender->wallet,
                    'gift_details' => $gift
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to send gift: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get user's gift inventory
     */
    public function getUserGiftInventory(Request $request)
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'filter_converted' => 'in:all,converted,unconverted',
            'filter_category' => 'string|max:50',
            'filter_rarity' => 'in:common,rare,epic,legendary',
            'sort_by' => 'in:received_at,converted_at,coin_value',
            'sort_order' => 'in:asc,desc'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $userId = $request->user_id;
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        $offset = ($page - 1) * $limit;

        $query = UserGiftInventory::where('user_id', $userId)
            ->with(['gift', 'sender.images']);

        // Apply filters
        if ($request->filter_converted === 'converted') {
            $query->converted();
        } elseif ($request->filter_converted === 'unconverted') {
            $query->unconverted();
        }

        if ($request->filter_category) {
            $query->byCategory($request->filter_category);
        }

        if ($request->filter_rarity) {
            $query->byRarity($request->filter_rarity);
        }

        // Apply sorting
        $sortBy = $request->sort_by ?? 'received_at';
        $sortOrder = $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Get total count for pagination
        $totalCount = $query->count();

        // Get paginated results
        $inventoryItems = $query->offset($offset)->limit($limit)->get();

        // Calculate statistics
        $stats = $this->calculateInventoryStats($userId);

        return response()->json([
            'status' => true,
            'message' => 'Inventory fetched successfully',
            'data' => [
                'inventory_items' => $inventoryItems,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'last_page' => ceil($totalCount / $limit)
                ],
                'statistics' => $stats
            ]
        ]);
    }

    /**
     * Convert multiple gifts to coins
     */
    public function convertGiftsToCoins(Request $request)
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
            'inventory_ids' => 'required|string'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $userId = $request->user_id;
        $inventoryIds = explode(',', $request->inventory_ids);
        $inventoryIds = array_map('intval', array_filter($inventoryIds));

        // Verify all inventory items belong to the user
        $ownedItems = UserGiftInventory::whereIn('id', $inventoryIds)
            ->where('user_id', $userId)
            ->pluck('id');

        $invalidIds = array_diff($inventoryIds, $ownedItems->toArray());
        if (!empty($invalidIds)) {
            return response()->json([
                'status' => false,
                'message' => 'Some inventory items do not belong to this user'
            ]);
        }

        // Convert gifts to coins
        $result = UserGiftInventory::convertGifts($inventoryIds, $userId);

        if ($result['success']) {
            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => [
                    'total_coins_received' => $result['total_coins'],
                    'converted_items' => $result['converted_items'],
                    'conversion_count' => count($result['converted_items'])
                ]
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => $result['message']
            ]);
        }
    }

    /**
     * Get gift conversion rates and available gifts
     */
    public function getGiftConversionData(Request $request)
    {
        $activeGifts = Gifts::active()->get();
        
        $conversionRates = $activeGifts->map(function ($gift) {
            return [
                'gift_id' => $gift->id,
                'name' => $gift->name,
                'image' => $gift->image,
                'coin_price' => $gift->coin_price,
                'category' => $gift->category,
                'rarity' => $gift->rarity,
                'rarity_color' => $gift->rarity_color,
                'formatted_price' => $gift->formatted_price
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Conversion data fetched successfully',
            'data' => [
                'available_gifts' => $activeGifts,
                'conversion_rates' => $conversionRates,
                'categories' => $activeGifts->pluck('category')->unique()->values(),
                'rarities' => ['common', 'rare', 'epic', 'legendary']
            ]
        ]);
    }

    /**
     * Get inventory statistics for user
     */
    public function getInventoryStats(Request $request)
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false, 
                'message' => $validator->errors()->first()
            ]);
        }

        $stats = $this->calculateInventoryStats($request->user_id);

        return response()->json([
            'status' => true,
            'message' => 'Statistics calculated successfully',
            'data' => $stats
        ]);
    }

    /**
     * Calculate inventory statistics for a user
     */
    private function calculateInventoryStats($userId)
    {
        $unconvertedItems = UserGiftInventory::where('user_id', $userId)
            ->unconverted()
            ->with('gift')
            ->get();

        $convertedItems = UserGiftInventory::where('user_id', $userId)
            ->converted()
            ->with('gift')
            ->get();

        $totalUnconvertedValue = $unconvertedItems->sum(function ($item) {
            return $item->quantity * ($item->gift->coin_price ?? 0);
        });

        $totalConvertedValue = $convertedItems->sum(function ($item) {
            return $item->quantity * ($item->gift->coin_price ?? 0);
        });

        // Group by category
        $categoryBreakdown = $unconvertedItems->groupBy(function ($item) {
            return $item->gift->category ?? 'general';
        })->map(function ($items, $category) {
            $totalValue = $items->sum(function ($item) {
                return $item->quantity * ($item->gift->coin_price ?? 0);
            });
            return [
                'category' => $category,
                'count' => $items->sum('quantity'),
                'total_value' => $totalValue
            ];
        })->values();

        // Group by rarity
        $rarityBreakdown = $unconvertedItems->groupBy(function ($item) {
            return $item->gift->rarity ?? 'common';
        })->map(function ($items, $rarity) {
            $totalValue = $items->sum(function ($item) {
                return $item->quantity * ($item->gift->coin_price ?? 0);
            });
            return [
                'rarity' => $rarity,
                'count' => $items->sum('quantity'),
                'total_value' => $totalValue
            ];
        })->values();

        return [
            'total_items_unconverted' => $unconvertedItems->sum('quantity'),
            'total_items_converted' => $convertedItems->sum('quantity'),
            'total_unconverted_value' => $totalUnconvertedValue,
            'total_converted_value' => $totalConvertedValue,
            'total_lifetime_value' => $totalUnconvertedValue + $totalConvertedValue,
            'unique_gifts_count' => $unconvertedItems->pluck('gift_id')->unique()->count(),
            'category_breakdown' => $categoryBreakdown,
            'rarity_breakdown' => $rarityBreakdown
        ];
    }
}