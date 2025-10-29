<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     * Includes pagination, search, and role filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Search by fullname, email, or phone
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('fullname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Filter by role
            if ($request->has('role') && $request->role !== 'all') { // Added 'all' check for front-end
                $query->where('role', $request->role);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate sort_by column to prevent SQL injection
            $allowedSortColumns = ['id', 'fullname', 'email', 'phone', 'role', 'created_at'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'created_at'; // Default to a safe column
            }

            $query->orderBy($sortBy, $sortOrder);

            // Flexible pagination
            $perPage = $request->get('per_page', 10);
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user list.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'fullname' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email|max:255',
                'phone' => 'nullable|string|regex:/^\d{10}$/|unique:users,phone',
                'password' => 'required|string|min:6|max:255',
                'address' => 'nullable|string|max:500',
                'role' => 'required|integer|in:0,1', 
            ]);

            $validated['password'] = Hash::make($validated['password']);
            $user = User::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully.',
                'data' => $user
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user.
     * Includes some user-related statistics.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $user = User::with(['orders' => function ($q) {
                $q->latest()->limit(5); // Only fetch a few recent orders
            }])->findOrFail($id);

            // User order statistics
            $stats = [
                'total_orders' => $user->orders()->count(),
                'total_spent' => $user->orders()->where('status', 'delivered')->sum('total_price'),
                'pending_orders' => $user->orders()->where('status', 'pending')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => array_merge($user->toArray(), ['stats' => $stats])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user details.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'fullname' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id)
                ],
                'phone' => [
                    'nullable',
                    'string',
                    'regex:/^\d{10}$/',
                    Rule::unique('users')->ignore($user->id)
                ],

                'password' => 'nullable|string|min:6|max:255',
                'address' => 'nullable|string|max:500',
                'role' => 'sometimes|required|integer|in:0,1',
            ]);

            // Only hash password if it's provided and not empty
            if (isset($validated['password']) && !empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']); // Remove password from validated data if not changed
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'data' => $user->fresh()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     * Performs a check for self-deletion and existing orders.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // Prevent self-deletion
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account.'
                ], 403); // Forbidden
            }

            // Check if user has any orders (assuming you want to prevent deletion if they do)
            // Consider soft deleting users or setting an 'is_active' flag instead of hard deleting.
            if ($user->orders()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a user with existing orders. Consider deactivating the account instead.'
                ], 400); // Bad Request
            }

            $user->delete(); // This will perform a soft delete if 'SoftDeletes' trait is used

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove multiple users from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:users,id' // Ensure all IDs exist
            ]);

            $idsToDelete = collect($validated['ids'])->filter(function ($id) {
                return $id !== Auth::id(); // Filter out current user's ID
            })->toArray();

            // Optional: Check for orders on bulk delete as well
            $usersWithOrders = User::whereIn('id', $idsToDelete)
                ->whereHas('orders')
                ->pluck('id');

            if ($usersWithOrders->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete users with existing orders. User IDs: ' . $usersWithOrders->implode(', '),
                    'cannot_delete_ids' => $usersWithOrders
                ], 400);
            }


            $count = User::destroy($idsToDelete); // Works for both soft and hard deletes based on model setup

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$count} users."
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk delete users.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
