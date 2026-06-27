<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductQuestion;
use App\Models\ProductQuestionAnswer;
use App\Models\ProductReview;
use App\Models\ProductReviewReply;
use App\Support\Reviews\ProductReviewModeration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorProductEngagementController extends Controller
{
    public function reviews(Request $request)
    {
        $vendorId = $this->vendorId();

        $query = ProductReview::query()
            ->whereHas('product', fn ($product) => $product->where('Vendor_Id', $vendorId))
            ->with([
                'product:id,Product_Name,Product_Name_Ar,Slug,Vendor_Id',
                'customer:id,Customer_Full_Name',
                'replies' => fn ($reply) => $reply->oldest(),
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('Status', $request->input('status'));
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('Title', 'like', "%{$search}%")
                    ->orWhere('Body', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('Product_Name', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function replyReview(Request $request, ProductReview $review)
    {
        $vendorId = $this->vendorId();
        abort_unless((int) optional($review->product)->Vendor_Id === $vendorId, 403, 'This review does not belong to your catalog.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $payload = ProductReviewModeration::vendorReplySnapshot(Auth::guard('vendor')->id(), $vendorId, $validated['body']);

        $reply = ProductReviewReply::create([
            'Product_Review_Id' => $review->id,
            ...$payload,
        ]);

        return response()->json(['data' => $reply], 201);
    }

    public function questions(Request $request)
    {
        $vendorId = $this->vendorId();

        $query = ProductQuestion::query()
            ->whereHas('product', fn ($product) => $product->where('Vendor_Id', $vendorId))
            ->with([
                'product:id,Product_Name,Product_Name_Ar,Slug,Vendor_Id',
                'customer:id,Customer_Full_Name',
                'answers' => fn ($answer) => $answer->oldest(),
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('Status', $request->input('status'));
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('Question', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('Product_Name', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function answerQuestion(Request $request, ProductQuestion $question)
    {
        $vendorId = $this->vendorId();
        abort_unless((int) optional($question->product)->Vendor_Id === $vendorId, 403, 'This question does not belong to your catalog.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $reply = ProductReviewModeration::vendorReplySnapshot(Auth::guard('vendor')->id(), $vendorId, $validated['body']);

        $answer = ProductQuestionAnswer::create([
            'Product_Question_Id' => $question->id,
            'Answer_Type' => $reply['Reply_Type'],
            'Vendor_User_Id' => $reply['Vendor_User_Id'],
            'Vendor_Id' => $reply['Vendor_Id'],
            'Body' => $reply['Body'],
            'Status' => $reply['Status'],
        ]);

        return response()->json(['data' => $answer], 201);
    }

    private function vendorId(): int
    {
        $user = Auth::guard('vendor')->user();
        $vendorId = $user->Vendor_Id ?? $user->vendor_id ?? null;

        abort_unless($vendorId, 403, 'Vendor ID not found for this user.');

        return (int) $vendorId;
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 20), 5), 100);
    }
}
