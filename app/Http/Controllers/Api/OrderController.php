<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Role;
use App\Models\Stage;
use App\Models\SplitOrder;
use App\Models\Uploads;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\Paginator;

class OrderController extends Controller
{
   
    public function orderList(Request $request)
    {
        try {
            $user = Auth::user();
            $role = $user->role->name ?? null;
            $stageId = $user->role->id ?? null;
            $menu_name = $request->menu_name ?? '';
            

            // --- Base Query ---
            if (!$role || !$stageId) {
                return response()->json([
                    'Resp_code' => false,
                    'Resp_desc' => 'User role or stage not found.'
                ], 403);
            }

            // --- Base Query ---
            $query = Order::query();

            // --- Common Filters ---
            if ($request->filled('assemblyNo')) {
                $query->where('assembly_no', $request->assemblyNo);
            }

            if ($request->filled('gmsoaNo')) {
                $query->where('soa_no', $request->gmsoaNo);
            }

            if ($request->filled('uniqueCode')) {
                $query->where('unique_code', $request->uniqueCode);
            }

            if ($request->filled('party')) {
                $query->where('party_name', $request->party);
            }

            if ($request->filled('assemblyDate')) {
                $dateInput = trim($request->assemblyDate);
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateInput)) {
                    $query->where('assembly_date', $dateInput);
                } elseif (preg_match('/^\d{2}\/\d{4}$/', $dateInput)) {
                    [$month, $year] = explode('/', $dateInput);
                    $query->where('assembly_date', 'like', "%/{$month}/{$year}");
                } elseif (preg_match('/^\d{4}$/', $dateInput)) {
                    $query->where('assembly_date', 'like', "%/{$dateInput}");
                } else {
                    return response()->json([
                        'Resp_code' => false,
                        'Resp_desc' => 'Invalid date format. Use dd/mm/yyyy, mm/yyyy, or yyyy.'
                    ], 400);
                }
            }

           

            // --- Fetch orders ---
            $orders = $query->orderBy('id', 'asc')->get();
     
            $filteredOrders = $orders->filter(function ($order) use ($stageId, $role, $menu_name) {
                $stageArray = json_decode($order->current_stage_id, true);
                $finishedValveRaw = $order->finished_valve;
                
                // normalize only for emptiness check (NOT case logic)
                $isFinishedValveSet = !is_null($finishedValveRaw) && trim($finishedValveRaw) !== '';
                
                // ================================
                // HARD RULE: FINISHED VALVE FLOW
                // ================================
                if ($isFinishedValveSet) {
                
                    // Menus BEFORE SVS
                    $beforeSVSMenus = [
                        'Planning',
                        'Material Issue',
                        'Semi QC',
                        'Phosphating QC',
                        'Assembly A',
                        'Assembly B',
                        'Assembly C',
                        'Assembly D',
                        'Testing1',
                        'Testing2',
                    ];
                
                    // If user is on BEFORE-SVS menu → BLOCK
                    // if ($role !== self::ROLE_PLANNING && in_array($menu_name, $beforeSVSMenus, true)) {
                    if (
                        $isFinishedValveSet &&
                        in_array($menu_name, $beforeSVSMenus, true) &&
                        $menu_name !== 'Planning'
                    ) {
                        return false;
                    }


                
                    // If order still has any stage BEFORE SVS → BLOCK
                    if (is_array($stageArray) && min($stageArray) < 5) {
                        return false;
                    }
                }

                
                
                // \Log::info($order->id .' '. $order->current_stage_id);
                $finishedValve = strtolower(trim($order->finished_valve ?? ''));
                
       
                // ========== ADMIN logic (correct way) ==========
                if ($role === self::ROLE_PLANNING) {
                    if ($menu_name === 'SVS') {
                        return $finishedValve === 'yes';
                    }
            
                    // Planning tab
                    if ($menu_name === 'Planning') {
                        return true;
                    }
                    
                    // Material Issue tab
                    if ($menu_name === 'Material Issue') {
                        return $finishedValve == '' || $order->finished_valve == null;
                    }
                    
                    if ($menu_name === 'Semi QC') {
                       $stageId = 3;
                    }
                    
                    if ($menu_name === 'Phosphating QC') {
                       $stageId = 4;
                    }
                    
                    if ($menu_name === 'Assembly A') {
                       $stageId = 16;
                    }
                    
                    if ($menu_name === 'Assembly B') {
                       $stageId = 17;
                    }
                    
                    if ($menu_name === 'Assembly C') {
                       $stageId = 18;
                    }
                    
                    if ($menu_name === 'Assembly D') {
                       $stageId = 19;
                    }
                    
                    if ($menu_name === 'Testing1') {
                       $stageId = 6;
                    }
                    
                    if ($menu_name === 'Testing2') {
                       $stageId = 7;
                    }
                    
                    if ($menu_name === 'Marking1') {
                       $stageId = 9;
                    }
                    
                    if ($menu_name === 'Marking2') {
                       $stageId = 10;
                    }
                    
                    if ($menu_name === 'PDI1') {
                       $stageId = 11;
                    }
                    
                    if ($menu_name === 'PDI2') {
                       $stageId = 12;
                    }
                    
                    if ($menu_name === 'TPI') {
                       $stageId = 14;
                    }
                    
                    if ($menu_name === 'Dispatch') {
                       $stageId = 13;
                    }
            
                    // Other role tabs → treat Admin as that role
                    return is_array($stageArray) && in_array($stageId, $stageArray);
                    
                }else{
                    
                    
                    if ($role === self::ROLE_SVS) {
                        return $finishedValve === 'yes';
                    }

                    if ($role === self::ROLE_MATERIAL_ISSUE) {
                        return $finishedValve === '' || $order->finished_valve === null;
                    }
                
                    return is_array($stageArray) && in_array($stageId, $stageArray);
                    
                }
              
            });
            
            $pendingCounts = [
                'totalOrders' => $filteredOrders->count(),
                'pendingMaterialIssue' => 0,
                'pendingSemiQC' => 0,
                'pendingPhosphatingQC' => 0,
                'pendingSVS' => 0,
                'pendingTesting1' => 0,
                'pendingTesting2' => 0,
                'pendingMarking1' => 0,
                'pendingMarking2' => 0,
                'pendingPDI1' => 0,
                'pendingPDI2' => 0,
                'pendingTPI' => 0,
                'pendingAssemblyA' => 0,
                'pendingAssemblyB' => 0,
                'pendingAssemblyC' => 0,
                'pendingAssemblyD' => 0,
            ];
            
            // Mapping of stageId → counter key
            $stageMap = [
                2 => 'pendingMaterialIssue',
                3 => 'pendingSemiQC',
                4 => 'pendingPhosphatingQC',
                5 => 'pendingSVS',
                6 => 'pendingTesting1',
                7 => 'pendingTesting2',
                9 => 'pendingMarking1',
                10 => 'pendingMarking2',
                11 => 'pendingPDI1',
                12 => 'pendingPDI2',
                14 => 'pendingTPI',
                16 => 'pendingAssemblyA',
                17 => 'pendingAssemblyB',
                18 => 'pendingAssemblyC',
                19 => 'pendingAssemblyD',
            ];

            
            if($menu_name !== 'Assembly'){

            }
       
            // --- Apply split logic and filter qty_pending > 0 ---
           
            $finalOrders = collect();
           
            foreach ($filteredOrders as $order) {
                
                $stages = json_decode($order->current_stage_id, true);
            
                if (is_array($stages)) {
                    foreach ($stages as $s) {
                        if (isset($stageMap[$s])) {
                            $pendingCounts[$stageMap[$s]]++;
                        }
                    }
                }
                
               
                // ================================
                // PLANNING → HIDE FULLY DISPATCHED
                // ================================
                if ($role === self::ROLE_PLANNING && $menu_name == 'Planning') {
                
                    $dispatch = DB::table('order_splits')->where('order_id', $order->id)->where('currentStage', 'Packaging')->selectRaw('COALESCE(SUM(remaining_qty),0) as remain_sum')->first();
                
                    if (($dispatch->remain_sum == $order->qty)) {
                        continue;
                    }
                
                    $order->totalQty = $order->qty;
                    $finalOrders->push($order);
                    continue;
                }

                
                /** -----------------------------
                 *  ADMIN — Material Issue TAB
                 * ------------------------------*/
                if ($role === self::ROLE_PLANNING && $menu_name == 'Material Issue') {
                    
                    $orderClone = clone $order;
                    $this->applyMaterialIssue($orderClone, $order->id, self::ROLE_MATERIAL_ISSUE);
                
                    if ($orderClone->qty_pending > 0) {
                        $orderClone->splitted_code = null;
                        $orderClone->split_id = null;
                        $orderClone->packaging = 0;
                        $orderClone->totalQty = $order->qty;
                        $finalOrders->push($orderClone);
                    }
                
                    continue;
                }

                // If role is Material Issue → show only main order, NOT separate split codes
                if ($role === self::ROLE_MATERIAL_ISSUE) {
            
                    $orderClone = clone $order;
            
                    $this->applyMaterialIssue($orderClone, $order->id, $role);
            
                    if ($orderClone->qty_pending > 0) {
                        $orderClone->splitted_code = null;
                        $orderClone->split_id = null;
                        $orderClone->packaging = 0;
                        $orderClone->totalQty = $order->qty;
                        $finalOrders->push($orderClone);
                    }
            
                    continue;
                    
                }
                
                // =========================================
                // ASSEMBLY ROLE → MERGE M and M-A rows by base M code
                
                // =========================================
                if ($role === self::ROLE_ASSEMBLY) {
                    
                    $splits = DB::table('order_splits')
                        ->where('order_id', $order->id)
                        ->where(function ($q) {
                            $q->Where('currentStage', 'Assembly');
                        })
                        ->orderBy('id', 'asc')
                        ->get();

                    if ($splits->isEmpty()) {
                        $clone = clone $order;
                        $clone->splitted_code = null;
                        $clone->qty = $order->qty;
                        $clone->qty_executed = 0;
                        $clone->qty_pending = $order->qty;
                        $clone->split_id = null;
                        $clone->packaging = 0;
                        $clone->totalQty = $order->qty;
                        $finalOrders->push($clone);
                        continue;
                    }
                
                    // Group by base M code (ex: ORD-0776-2025-3-M2)
                    $grouped = $splits->groupBy(function ($row) {
                        if (preg_match('/^(.*-M\d+)/', $row->split_code, $m)) {
                            return $m[1];
                        }
                        return $row->split_code;
                    });
                
                    foreach ($grouped as $baseM => $rows) {
                
                        $orderQty = $order->qty;
                
                        // Raw totals
                        $totalQty = $rows->sum('qty');
                        $assigned = $rows->sum('assigned_qty');
                
                        // Enforce qty cannot exceed main order qty
                        if ($totalQty > $orderQty) {
                            $totalQty = $orderQty;
                        }
                
                        // Same for executed
                        if ($assigned > $orderQty) {
                            $assigned = $orderQty;
                        }
                
                        // Pending cap
                        $pending = max($totalQty - $assigned, 0);
                
                        if ($pending > 0) {
                
                            $clone = clone $order;
                            $clone->splitted_code = $baseM;
                            $clone->qty = $totalQty;
                            $clone->qty_executed = $assigned;
                            $clone->qty_pending = $pending;
                
                            // Pick the main M-row if available
                            $baseRow = $rows->first(function($r) use ($baseM) {
                                return $r->split_code === $baseM;
                            });
                
                            $clone->split_id = $baseRow ? $baseRow->id : $rows->first()->id;
                            $clone->packaging = $baseRow ? $baseRow->is_packaging : $rows->first()->is_packaging;
                            $clone->totalQty = $order->qty;
                            $finalOrders->push($clone);
                        }
                    }
                
                    continue;
                    
                }
                
              
                // ================================
                // NORMAL ROLES → GROUP SPLITS
                // ================================
                if ($role == self::ROLE_PLANNING) {
                    $role = $menu_name; 
                }
                
                $splits = DB::table('order_splits')
                    ->where('order_id', $order->id)
                    ->where('currentStage', $role)
                    ->orderBy('id', 'asc')
                    ->get();
                    
                    
                
                $hasAnySplit = $splits->isNotEmpty();
                
                if (!$hasAnySplit) {
                    $orderClone = clone $order;
                    $orderClone->split_code    = null;
                    $orderClone->qty           = $order->qty;
                    $orderClone->qty_executed  = 0;
                    $orderClone->qty_pending   = $order->qty;
                    $orderClone->totalQty      = $order->qty;
                    $finalOrders->push($orderClone);
                    continue;
                }
                
                // Group split codes
                $groupedSplits = $splits->groupBy('split_code');
                
                foreach ($groupedSplits as $splitCode => $rows) {
                
                    // ================================
                    // SPECIAL CASE FOR DISPATCH
                    // ================================
                    if ($role === 'Dispatch') {
                
                        $qty_executed = $rows->where('currentStage', 'Dispatch')->sum('remaining_qty');
                        $qty_pending  = $rows->where('currentStage', '!=', 'Dispatch')->sum('remaining_qty');
                
                        if ($qty_executed > 0) {
                            $clone = clone $order;
                            $clone->splitted_code = $splitCode;
                            $clone->qty           = $rows->sum('qty');
                            $clone->qty_executed  = $qty_executed;
                            $clone->qty_pending   = $order->qty - $qty_executed;
                            $clone->split_id      = $rows->first()->id;
                            $clone->packaging      = $rows->first()->is_packaging;
                            $clone->totalQty      = $order->qty;
                            $finalOrders->push($clone);
                        }
                
                        continue;
                    }
                
                    // ================================
                    // NORMAL STAGE LOGIC
                    // ================================
                    $totalQty    = $rows->sum('qty');
                    $executedQty = $rows->sum('assigned_qty');
                    $pendingQty  = max($totalQty - $executedQty, 0);
                
                    if ($pendingQty > 0) {
                        $clone = clone $order;
                        $clone->splitted_code = $splitCode;
                        $clone->qty           = $totalQty;
                        $clone->qty_executed  = $executedQty;
                        $clone->qty_pending   = $pendingQty;
                        $clone->split_id      = $rows->first()->id;
                        $clone->packaging      = $rows->first()->is_packaging;
                        $clone->totalQty      = $order->qty;
                        $finalOrders->push($clone);
                    }
                }

            }

            $page     = max((int) $request->get('page', 1), 1);
            $perPage  = max((int) $request->get('per_page', 20), 1);
    
            $total    = $finalOrders->count();
            $offset   = ($page - 1) * $perPage;
    
            $pagedData = $finalOrders->slice($offset, $perPage)->values();
            
            return response()->json([
                'Resp_code' => true,
                'Resp_desc' => 'Order list fetched successfully.',
                'data' => $finalOrders->values(),
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => ceil($total / $perPage),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::info($e->getMessage());
            return response()->json([
                'Resp_code' => false,
                'Resp_desc' => 'Something went wrong while fetching orders.',
                'error'     => $e->getMessage()
            ], 500);
        }
    }
    
    public function getAssemblyCSVSSplits()
    {
        $data = DB::table('orders as o')
            ->join('order_splits as s', 's.order_id', '=', 'o.id')
            ->where('o.assembly_no', 'C')
            ->where('s.currentStage', 'SVS')
            ->where('s.remaining_qty', '>', 0)
            ->select([
                // ---- ORDER FIELDS ----
                'o.id',
                'o.order_no',
                'o.assembly_no',
                'o.soa_no',
                'o.soa_sr_no',
                'o.assembly_date',
                'o.unique_code',
                'o.party_name',
                'o.customer_po_no',
                'o.code_no',
                'o.product',
                'o.qty',
                'o.po_qty',
                'o.qty_executed',
                'o.qty_pending',
                'o.finished_valve',
                'o.gm_logo',
                'o.name_plate',
                'o.special_notes',
                'o.product_spc1',
                'o.product_spc2',
                'o.product_spc3',
                'o.inspection',
                'o.painting',
                'o.remarks',
                'o.urgent',
                'o.current_stage_id',
                'o.status',
                'o.created_by',
                'o.created_at',
                'o.updated_at',
    
                // ---- SPLIT FIELDS ----
                's.id as split_id',
                's.split_code',
                's.qty as qty',
                's.assigned_qty as qty_executed',
                's.remaining_qty as qty_pending',
    
                // ---- DERIVED ----
                'o.qty as totalQty'
            ])
            ->orderBy('o.id', 'desc')
            ->get();
    
        return response()->json([
            'Resp_code' => true,
            'data' => $data
        ]);
    }

    private function applyMaterialIssue(&$order, $order_id, $role)
    {
        // Fetch all splits for this order & stage (complete or not)
        $splits = DB::table('order_splits')
            ->where('order_id', $order_id)
            ->where('currentStage', $role)
            ->get();

        if ($splits->isNotEmpty()) {
    
            $first = $splits->first();
    
            // Total qty comes from first split
            $total_qty = $first->qty;
    
            // Total executed = sum of assigned_qty
            $qty_executed = $splits->sum('assigned_qty');
    
            // Pending = max(total - executed, 0)
            $qty_pending = max($total_qty - $qty_executed, 0);
    
            $order->qty          = $total_qty;
            $order->qty_executed = $qty_executed;
            $order->qty_pending  = $qty_pending;
    
        } else {
    
            // No splits found → fallback
            $order->qty          = $order->qty ?? 0;
            $order->qty_executed = 0;
            $order->qty_pending  = $order->qty ?? 0;
        }

    }

    private function applyCommonSplit(&$order, $order_id, $role)
    {
        // 🔹 Find the active (not complete) record for this stage
        $activeSplit = DB::table('order_splits')
            ->where('order_id', $order_id)
            ->where('currentStage', $role)
            ->where('isComplete', 0)
            ->where('remaining_qty','!=',0)
            // ->where('assigned_qty','!=',0)
            ->where('status', 0)
            ->latest('id')
            ->first();

        // 🔹 Fallback: last completed record (for reference)
        $lastComplete = DB::table('order_splits')
            ->where('order_id', $order_id)
            ->where('currentStage', $role)
            ->where('isComplete', 1)
            ->where('remaining_qty',0)
            ->latest('id')
            ->first();

        if ($activeSplit) {
            // Current stage has an active (in-progress) entry
            $order->qty          = $activeSplit->qty ?? 0;
            $order->qty_pending  = $activeSplit->remaining_qty ?? 0;
            $order->qty_executed = $activeSplit->assigned_qty ?? 0;
        } elseif ($lastComplete) {
            // Show last completed state if no active entry found
            $order->qty          = $lastComplete->qty ?? 0;
            $order->qty_pending  = 0;
            $order->qty_executed = $lastComplete->assigned_qty ?? 0;
        } else {
            // Default fallback — no split history
            $order->qty          = $order->qty ?? 0;
            $order->qty_pending  = $order->qty ?? 0;
            $order->qty_executed = 0;
        }
    }

    public function uploadOrderFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'Resp_code' => 'false',
                'Resp_desc' => $validator->errors()->first(),
            ], 422);
        }

        try {
            
            $data = Excel::toArray([], $request->file('file'))[0];
            if (empty($data) || count($data) < 2) {
                return response()->json([
                    'Resp_code' => 'false',
                    'Resp_desc' => 'The file is empty or has no data rows.',
                ]);
            }
        
            $headers = array_map(fn($h) => trim(strtolower($h)), $data[0]);
            unset($data[0]);
        
            // ✅ Fix date column issue here
        
            $assemblyIndex = array_search('assembly date', $headers, true);
            if ($assemblyIndex !== false) {
                foreach ($data as &$row) {
                    if (isset($row[$assemblyIndex]) && is_numeric($row[$assemblyIndex]) && (float)$row[$assemblyIndex] > 0) {
                        try {
                            $dt = PhpSpreadsheetDate::excelToDateTimeObject((float)$row[$assemblyIndex]);
                            $row[$assemblyIndex] = $dt->format('d-m-Y');
                        } catch (\Exception $e) {}
                    } else {
                        if (isset($row[$assemblyIndex])) {
                            $row[$assemblyIndex] = trim((string)$row[$assemblyIndex]);
                        }
                    }
                }
                unset($row);
            }

            
            $requiredHeaders = [
                'assembly line', 'soa no.', 'sr.no.', 'assembly date','unique code','splitted code',
                'party', 'customer po no.', 'code no', 'product','po qty','qty',
                'qty exe.', 'qty pending', 'finished valve', 'gm logo',
                'name plate','special notes', 'product spcl 1', 'product spcl 2', 'product spcl 3',
                'inspection', 'painting', 'remarks','delivery date'
            ];

            $missingHeaders = array_diff($requiredHeaders, array_map('strtolower', $headers));
            $extraHeaders   = array_diff(array_map('strtolower', $headers), $requiredHeaders);

            if (!empty($missingHeaders) || !empty($extraHeaders)) {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Loop through uploaded headers
                foreach ($headers as $i => $header) {
                    $colNumber = $i + 1;
                    $colLetter = Coordinate::stringFromColumnIndex($colNumber);
                    $sheet->setCellValue("{$colLetter}1", $header);

                    $lowerHeader = strtolower(trim($header));

                    // Case-insensitive check if exists in required headers
                    if (in_array($lowerHeader, $requiredHeaders)) {
                        $correctHeader = $requiredHeaders[array_search($lowerHeader, $requiredHeaders)];
                        if ($header !== $correctHeader) {
                            // Case mismatch
                            $nextColLetter = Coordinate::stringFromColumnIndex($colNumber + 1);
                            $sheet->setCellValue("{$nextColLetter}1", "Correct header: {$correctHeader}");

                            $sheet->getStyle("{$colLetter}1")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFFFA500'); // orange
                        }
                    } else {
                        // Unexpected header
                        $nextColLetter = Coordinate::stringFromColumnIndex($colNumber + 1);
                        $sheet->setCellValue("{$nextColLetter}1", "Unexpected header: {$header}");

                        $sheet->getStyle("{$colLetter}1")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB(Color::COLOR_RED);
                        $sheet->getStyle("{$colLetter}1")->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                    }
                }

                // Add missing headers at right
                $startCol = count($headers) + 2;
                foreach ($missingHeaders as $j => $missing) {
                    $colLetter = Coordinate::stringFromColumnIndex($startCol + $j);
                    $sheet->setCellValue("{$colLetter}1", "Missing: {$missing}");
                    $sheet->getStyle("{$colLetter}1")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB(Color::COLOR_YELLOW);
                }

                // Write uploaded rows back
                $rowIndex = 2;
                foreach ($data as $row) {
                    foreach ($row as $i => $cell) {
                        $colLetter = Coordinate::stringFromColumnIndex($i + 1);
                        $sheet->setCellValue("{$colLetter}{$rowIndex}", $cell);
                    }
                    $rowIndex++;
                }

                // Save in /public/errors
                $errorsPath = public_path('errors');
                if (!file_exists($errorsPath)) {
                    mkdir($errorsPath, 0777, true);
                }

                $fileName = 'header_errors_' . time() . '.xlsx';
                $filePath = $errorsPath . '/' . $fileName;

                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);

                $fileUrl = url('public/errors/' . $fileName);

                return response()->json([
                    'Resp_code' => 'false',
                    'Resp_desc' => 'Header mismatch found.',
                    'file_url'  => $fileUrl,
                ]);
            }


         
            // --- Row validation ---
            $validAssemblyLines = ['a', 'b', 'c', 'd'];
        
            $errors = [];
            $insertData = [];
            $rowNumber = 2;

            // data starts from second row (1 = header)
            // keep this BEFORE foreach
            $duplicateCheck = [];
            $fileCodeTracker = []; // Tracks duplicates inside file

            foreach ($data as $row) {

                $rowError = [];
            
                $rowData = array_combine($headers, $row);

                // ==========================================
                // 🚫 Skip blank rows (don't insert anything)
                // ==========================================
                $requiredKeys = ['soa no.', 'sr.no.', 'assembly date'];
            
                $allBlank = true;
                foreach ($requiredKeys as $key) {
                    if (!empty(trim($rowData[$key] ?? ''))) {
                        $allBlank = false;
                        break;
                    }
                }
            
                // also check if entire row empty
                $wholeRowEmpty = true;
                foreach ($rowData as $value) {
                    if (!empty(trim($value))) {
                        $wholeRowEmpty = false;
                        break;
                    }
                }
            
                if ($allBlank || $wholeRowEmpty) {
                    // skip silently, don't validate, don't insert
                    $rowNumber++;
                    continue;
                }
               
            
                // Existing validations
                if (!empty($rowData['assembly line']) && !in_array(strtolower(trim($rowData['assembly line'])), $validAssemblyLines)) {
                    $rowError['assembly line'] = 'Invalid Assembly Line (only A, B, C, D allowed)';
                }
            
                if (!empty($rowData['sr.no.']) && !ctype_digit((string)$rowData['sr.no.'])) {
                    $rowError['sr.no.'] = 'sr.no. must be an integer';
                }
            
                if (!empty($rowData['assembly date'])) {
                    $date = \DateTime::createFromFormat('d-m-Y', trim($rowData['assembly date']));
                    if (!$date || $date->format('d-m-Y') !== trim($rowData['assembly date'])) {
                        $rowError['assembly date'] = 'Invalid date format (expected dd-mm-yyyy)';
                    }
                }
            
                if (!empty($rowData['finished valve']) && !in_array(strtolower(trim($rowData['finished valve'])), ['yes', ''])) {
                    $rowError['finished valve'] = 'Finished Valve must be Yes or blank';
                }
            
                // --- Duplicate Row Validation ---
               
                
                
                // ------------------------------------------
                // existing field validations
                // ------------------------------------------
                if (!empty($rowData['assembly line']) &&
                    !in_array(strtolower(trim($rowData['assembly line'])), $validAssemblyLines)
                ) {
                    $rowError['assembly line'] = 'Invalid Assembly Line (only A, B, C, D allowed)';
                }
            
                if (!empty($rowData['sr.no.']) &&
                    !ctype_digit((string)$rowData['sr.no.'])
                ) {
                    $rowError['sr.no.'] = 'sr.no. must be an integer';
                }
            
               
                // ------------------------------------------
                // ✅ DUPLICATE VALIDATION (WITH ASSEMBLY DATE)
                // ------------------------------------------
                
                $g = $this->clean($rowData['soa no.']);
                $s = $this->clean($rowData['sr.no.']);
                $a = $this->clean($rowData['assembly date']); // dd-mm-YYYY
                
                if ($g !== '' && $s !== '' && $a !== '') {
                
                    // 🔑 composite key INCLUDING assembly date
                    $comboKeyWithDate = "{$g}|{$s}|{$a}";
                
                    // 1️⃣ Duplicate inside uploaded file (STRICT)
                    if (isset($duplicateCheck[$comboKeyWithDate])) {
                        $rowError['duplicate'] =
                            "Duplicate row found: GMSOA {$g}, SOA SR {$s}, Assembly Date {$a}";
                    } else {
                        $duplicateCheck[$comboKeyWithDate] = true;
                    }
                
                    // 2️⃣ Duplicate already in database (STRICT)
                    if (empty($rowError['duplicate'])) {
                
                        $exists = Order::where('soa_no', $g)
                            ->where('soa_sr_no', $s)
                            ->where('assembly_date', $a)
                            ->exists();
                
                        if ($exists) {
                            $rowError['duplicate'] =
                                "Already exists in system: GMSOA {$g}, SOA SR {$s}, Assembly Date {$a}";
                        }
                    }
                }



            
                // Store errors or prepare insert data
                if (!empty($rowError)) {
                    $errors[$rowNumber] = $rowError;
                } else {
                    // your existing insert logic...
                    $soaNo     = trim($rowData['soa no.']);
                    $soaSrNo   = trim($rowData['sr.no.']);
                    $lastFour  = substr(str_pad($soaNo, 4, '0', STR_PAD_LEFT), -4);
                    $year      = date('Y');
                    $assemblyDate = trim($rowData['assembly date']);

                    // Base code format: ORD-{GMSOA}-{SOA_SR}-{YEAR}
                    $baseCode = "ORD-{$soaNo}-{$soaSrNo}-" . date('Y');
                    
                    // Generate next unique code
                    $uniqueCode = $this->generateNextUniqueCode(
                        $soaNo,
                        $soaSrNo,
                        $assemblyDate,
                        $baseCode,
                        $fileCodeTracker
                    );


            
                    $status = (isset($rowData['finished valve']) && strtolower($rowData['finished valve']) == 'yes')
                        ? self::ROLE_SVS : self::ROLE_MATERIAL_ISSUE;
            
                    $current_stage_id = (isset($rowData['finished valve']) && strtolower($rowData['finished valve']) == 'yes')
                        ? '7' : '2';
            
                  $rowData = array_combine($headers, $row);

                  /* FIX DELIVERY DATE */
                  if (!empty($rowData['delivery date'])) {

                      if (is_numeric($rowData['delivery date'])) {

                          $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rowData['delivery date']);
                          $rowData['delivery date'] = $date->format('d-m-Y');

                      } else {

                          $value = trim($rowData['delivery date']);

                          $date = \DateTime::createFromFormat('d-m-Y', $value);

                          if ($date) {
                              $rowData['delivery date'] = $date->format('d-m-Y');
                          }
                      }
                  }
                  
                  
                    $insertData[] = [
                        'order_no' => $uniqueCode,
                        'assembly_no' => $rowData['assembly line'] ?? null,
                        'soa_no' => $rowData['soa no.'] ?? null,
                        'soa_sr_no' => $rowData['sr.no.'] ?? null,
                        'assembly_date' => $rowData['assembly date'] ?? null,
                        'unique_code' => $uniqueCode,
                        'splitted_code' => '',
                        'party_name' => $rowData['party'] ?? null,
                        'customer_po_no' => $rowData['customer po no.'] ?? null,
                        'code_no' => $rowData['code no'] ?? null,
                        'product' => $rowData['product'] ?? null,
                        'po_qty' => $rowData['po qty'] ?? 0,
                        'qty' => $rowData['qty'] ?? 0,
                        'qty_executed' => $rowData['qty exe.'] ?? 0,
                        'qty_pending' => $rowData['qty pending'] ?? 0,
                        'finished_valve' => $rowData['finished valve'] ?? '',
                        'gm_logo' => $rowData['gm logo'] ?? null,
                        'name_plate' => $rowData['name plate'] ?? null,
                        'special_notes' => $rowData['special notes'] ?? null,
                        'product_spc1' => $rowData['product spcl 1'] ?? null,
                        'product_spc2' => $rowData['product spcl 2'] ?? null,
                        'product_spc3' => $rowData['product spcl 3'] ?? null,
                        'inspection' => $rowData['inspection'] ?? null,
                        'painting' => $rowData['painting'] ?? null,
                        'remarks' => $rowData['remarks'] ?? null,
                         'deliveryDT' => $rowData['delivery date'] ?? null,
                        'current_stage_id' => $current_stage_id,
                        'status' => $status,
                        'created_by' => Auth::id() ?? 0,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            
                $rowNumber++;
            }


            if (!empty($errors)) {
                // --- build error excel with same structure ---
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // write headers
                $colNumber = 1;
                foreach ($headers as $header) {
                    $colLetter = Coordinate::stringFromColumnIndex($colNumber);
                    $sheet->setCellValue("{$colLetter}1", $header);
                    $colNumber++;
                }

                // add error column
                $errorColLetter = Coordinate::stringFromColumnIndex($colNumber);
                $sheet->setCellValue("{$errorColLetter}1", 'Error');

                // fill rows
                $rowIndex = 2;
                foreach ($data as $i => $row) {
                    $colNumber = 1;
                    foreach ($row as $cell) {
                        $colLetter = Coordinate::stringFromColumnIndex($colNumber);
                        $sheet->setCellValue("{$colLetter}{$rowIndex}", $cell);
                        $colNumber++;
                    }

                    // add error text if exists
                    if (isset($errors[$rowIndex])) {
                        $messages = [];
                        foreach ($errors[$rowIndex] as $field => $msg) {
                            $messages[] = ucfirst($field) . ': ' . $msg;
                        }
                        $sheet->setCellValue("{$errorColLetter}{$rowIndex}", implode(' | ', $messages));

                        // highlight error row
                        $sheet->getStyle("{$errorColLetter}{$rowIndex}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFFFA500');
                    }

                    $rowIndex++;
                }

                // save file
                $errorsPath = public_path('errors');
                if (!file_exists($errorsPath)) {
                    mkdir($errorsPath, 0777, true);
                }

                $fileName = 'order_data_errors_' . time() . '.xlsx';
                $filePath = $errorsPath . '/' . $fileName;
                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);

                $fileUrl = url('public/errors/' . $fileName);

                return response()->json([
                    'Resp_code' => 'false',
                    'Resp_desc' => 'Validation errors found in uploaded data.',
                    'file_url'  => $fileUrl,
                ]);
            }

            // Save valid rows
            Order::insert($insertData);
            
            // full path of uploaded file
            $uploadedFile = $request->file('file');
            $storedFileName = time() . "_" . $uploadedFile->getClientOriginalName();
            $uploadedFile->move(public_path('uploads/orders'), $storedFileName);
            
            // Calculate totals
            $totalRows   = count($data);              // excluding header already
            $successRows = count($insertData);        // rows ready to insert
            $failedRows  = $totalRows - $successRows; // row errors + skipped
            
            // Store log
            Uploads::create([
                'file_name'    => $storedFileName,
                'uploaded_by'  => Auth::id(),
                'total_rows'   => $totalRows,
                'success_rows' => $successRows,
                'failed_rows'  => $failedRows,
            ]);

            return response()->json([
                'Resp_code' => 'true',
                'Resp_desc' => 'File imported successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'Resp_code' => 'false',
                'Resp_desc' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    private function checkDuplicateGmsoaSsr(array $rows, array $headers): array 
    {
    
        $fileSeen = [];
        $duplicatesInFile = [];
        $pairs = [];
    
        foreach ($rows as $index => $row) {
    
            $rowData = array_combine($headers, $row);
    
            $gmsoa = trim($rowData['soa no.'] ?? '');
            $soaSr = trim($rowData['sr.no.'] ?? '');
    
            if ($gmsoa === '' || $soaSr === '') {
                continue;
            }
    
            $key = $gmsoa . '|' . $soaSr;
    
            // 🔴 Duplicate inside file
            if (isset($fileSeen[$key])) {
                $duplicatesInFile[] = [
                    'row'   => $index + 1,
                    'gmsoa' => $gmsoa,
                    'soa'   => $soaSr,
                ];
            } else {
                $fileSeen[$key] = true;
                $pairs[] = [$gmsoa, $soaSr];
            }
        }
    
        // 🔴 Duplicate in DB
        $duplicatesInDb = Order::where(function ($q) use ($pairs) {
            foreach ($pairs as [$gmsoa, $soaSr]) {
                $q->orWhere(function ($sub) use ($gmsoa, $soaSr) {
                    $sub->where('soa_no', $gmsoa)
                        ->where('soa_sr_no', $soaSr);
                });
            }
        })->get(['soa_no', 'soa_sr_no']);
    
        return [
            'file' => $duplicatesInFile,
            'db'   => $duplicatesInDb,
        ];
    }

    private function generateNextUniqueCode($soaNo,$soaSrNo, $assemblyDate,$baseCode, &$fileCodeTracker) 
    {
       
        $dbCount = Order::where('soa_no', $soaNo)->where('soa_sr_no', $soaSrNo)->count();
        $key = "{$soaNo}|{$soaSrNo}";
        $fileCount = $fileCodeTracker[$key] ?? 0;
        $nextIndex = $dbCount + $fileCount;
        $fileCodeTracker[$key] = $fileCount + 1;
        if ($nextIndex === 0) {
            return $baseCode;
        }
        // Second onwards → -1, -2, -3...
        return $baseCode . '-' . $nextIndex;
    }

    public function getOrderDetail($order_id)
    {
        try {
            $order = Order::find($order_id);

            if (!$order) {
                return response()->json([
                    'Resp_code' => false,
                    'Resp_desc' => 'Order not found for given Order ID.',
                ], 404);
            }

            $user = auth()->user();
            $role = is_object($user->role)
                ? ($user->role->name ?? '')
                : ($user->role ?? '');

            // --- Material Issue special case ---
            if ($role === self::ROLE_MATERIAL_ISSUE) {
                $this->applyMaterialIssue($order, $order_id, $role);
            } 
            // --- Common logic for other roles ---
            else {
                $this->applyCommonSplit($order, $order_id, $role);
            }

            return response()->json([
                'Resp_code' => true,
                'Resp_desc' => 'Order details fetched successfully.',
                'data'      => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'Resp_code' => false,
                'Resp_desc' => 'Something went wrong while fetching order details.',
                'error'     => $e->getMessage(),
            ], 500);
        }
    }

    public function markUrgent(Request $request)
    {
        $request->validate([
            'orderId' => 'required|integer|exists:orders,id',
            'urgent'   => 'required|in:0,1',
        ]);

        try {
            $order = Order::find($request->orderId);
            $order->urgent = $request->urgent;
            $order->save();

            return response()->json([
                'Resp_code' => 'true',
                'Resp_desc' => $request->urgent == 1 
                    ? 'Order marked as urgent.'
                    : 'Order marked as normal.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'Resp_code' => 'false',
                'Resp_desc' => 'Failed to update order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assignOrder(Request $request)
    {
    
        $request->validate([
            'orderId'     => 'required|exists:orders,id',
            'split_id'    => 'nullable|exists:order_splits,id',
            'executedQty' => 'required|numeric|min:1',
            'totalQty'    => 'required|numeric|min:1',
            'nextSteps'   => 'required',
            'currentSteps'   => 'required',
        ]);

        DB::beginTransaction();
    
        try {
            
            $user     = Auth::user();
            $roleName = $request->currentSteps;
    
            $fromStage = Stage::where('name', $request->currentSteps)->first();
            $order     = Order::findOrFail($request->orderId);
            $toStage   = Stage::where('name', $request->nextSteps)->first();
    
            if (!$toStage) {
                throw new \Exception("Invalid next stage: " . $request->nextSteps);
            }
    
            $assignedQty = $request->executedQty;
            $totalQty    = $request->totalQty;
    
            // -------------------------------------------
            // FETCH ACTIVE SPLIT (existing or brand new)
            // -------------------------------------------
            $activeSplit = null;
            $handledCustomSplit = false;

    
            if ($request->filled('split_id')) {
                $activeSplit = DB::table('order_splits')
                    ->where('id', $request->split_id)
                    ->first();
            } else {
                $activeSplit = DB::table('order_splits')
                    ->where('order_id', $order->id)
                    ->where('currentStage', $fromStage->name)
                    ->where('isComplete', 0)
                    ->orderBy('id', 'asc')
                    ->first();
            }
    
            // If split is already finished → treat as no active split
            if ($activeSplit && $activeSplit->remaining_qty == 0) {
                $activeSplit = null;
            }
    
            // -------------------------------------------
            // SPECIAL RULE FOR MATERIAL ISSUE & ASSEMBLY
            // -------------------------------------------
            if (in_array($roleName, ['Material Issue', 'Assembly', 'Assembly A', 'Assembly B', 'Assembly C','Assembly D'])) {
    
                // Force new split if partial assignment
                if ($activeSplit && $activeSplit->qty != $assignedQty) {
                    $activeSplit = null;
                }
    
                if (!$activeSplit && $assignedQty < $totalQty) {
                    $activeSplit = null;
                }
            }
    
            // -------------------------------------------
            // GENERATE BASE CODE
            // -------------------------------------------
            $soaNo    = $order->soa_no ?? '';
            $soaSrNo  = $order->soa_sr_no ?? '';
            $year     = now()->year;
            $lastFour = substr(str_pad($soaNo, 4, '0', STR_PAD_LEFT), -4);
    
            // $baseCode = "ORD-{$lastFour}-{$year}-{$soaSrNo}";
            $baseCode = $order->unique_code;
    
            // Counters
            $materialCount = DB::table('order_splits')
                ->where('order_id', $order->id)
                ->where('currentStage', 'Material Issue')
                ->count();
    
            $assemblyCount = DB::table('order_splits')
                ->where('order_id', $order->id)
                ->where('currentStage', 'Assembly')
                ->count();
                
                        // Load order
            // For Material Issue or Assembly
            $stageName = $request->currentStage;
            
            // Total qty
            $orderQty = $order->qty;
            
            // Already assigned qty for this stage
            $alreadyAssigned = DB::table('order_splits')
                ->where('order_id', $order->id)
                ->where('currentStage', $stageName)
                ->sum('assigned_qty');
           
            
            $isFullyAssigned = $alreadyAssigned >= $orderQty;
            
            if (!$isFullyAssigned) {    
                // -------------------------------------------
                // SPLIT CODE LOGIC
                // -------------------------------------------
                if ($activeSplit) {
    
                    \Log::info("✔ Using existing activeSplit", [
                        'order_id'     => $order->id,
                        'role'         => $roleName,
                        'active_split' => $activeSplit->split_code,
                        'assigned_qty' => $assignedQty
                    ]);
                
                    // Use same split for full assignment
                    $splitCode = $activeSplit->split_code;
                
                } else {
                
                    if ($roleName === 'Material Issue') {
                
                        $splitCode = $baseCode . "-M" . ($materialCount + 1);
                
                       
                    } else if (in_array($roleName, ['Assembly A','Assembly B','Assembly C','Assembly D'])) {
    
                        $handledCustomSplit = true;
                    
                        if (!$request->filled('split_id')) {
                            throw new \Exception("split_id is required for Assembly.");
                        }
                    
                        // Fetch selected split
                        $inputSplit = DB::table('order_splits')
                            ->where('id', $request->split_id)
                            ->where('order_id', $order->id)
                            ->first();
                    
                        if (!$inputSplit) {
                            throw new \Exception("Split not found for this order.");
                        }
                    
                        // ------------------------------
                        // 1. Extract BASE MATERIAL CODE
                        // ------------------------------
                        if (preg_match('/^(.*-M\d+)/', $inputSplit->split_code, $m)) {
                            $materialBaseCode = $m[1];
                        } else {
                            $materialBaseCode = $inputSplit->split_code;
                        }
                    
                        // ------------------------------
                        // 2. Fetch MAIN MATERIAL ROW
                        // ------------------------------
                        $mainM = DB::table('order_splits')
                            ->where('order_id', $order->id)
                            ->where('split_code', $materialBaseCode)
                            ->first();
                    
                        if (!$mainM) {
                            throw new \Exception("Main Material Issue split not found for: " . $materialBaseCode);
                        }
                    
                        // ------------------------------
                        // 3. Determine next A-number
                        // ------------------------------
                        $existingAssemblySplits = DB::table('order_splits')
                            ->where('order_id', $order->id)
                            ->where('split_code', 'like', $materialBaseCode . '-A%')
                            ->pluck('split_code')
                            ->toArray();
                    
                        $nextA = 1;
                        if (!empty($existingAssemblySplits)) {
                            $last = collect($existingAssemblySplits)->sort()->last();
                            preg_match('/-A(\d+)$/', $last, $matches);
                    
                            if (!empty($matches[1])) {
                                $nextA = intval($matches[1]) + 1;
                            }
                        }
                    
                        $childCode = $materialBaseCode . "-A" . $nextA;
                    
                        // ------------------------------
                        // 4. CHECK IF SAME A-SPLIT EXISTS
                        // ------------------------------
                        $existingSameAssembly = DB::table('order_splits')
                            ->where('order_id', $order->id)
                            ->where('split_code', $materialBaseCode)     // ✔️ correct split code check
                            ->where('currentStage', $fromStage->name)
                            ->first();
                            
                        // Validate assigned qty is not greater than available quantity
                        $availableQty = $existingSameAssembly
                            ? $existingSameAssembly->remaining_qty
                            : $mainM->remaining_qty;
                        
                        if ($assignedQty > $availableQty) {
                            throw new \Exception("Assigned quantity cannot be greater than available quantity ($availableQty).");
                        }
    
    
                        if ($existingSameAssembly) {
                    
                            $newAssigned  = $existingSameAssembly->assigned_qty + $assignedQty;
                            $newRemaining = max($existingSameAssembly->qty - $newAssigned, 0);
                    
                            DB::table('order_splits')
                                ->where('id', $request->split_id)
                                ->where('order_id', $order->id)
                                ->where('currentStage', $fromStage->name)
                                ->update([
                                    'assigned_qty'  => $newAssigned,
                                    'remaining_qty' => $newRemaining,
                                    'isComplete'    => ($newRemaining == 0) ? 1 : 0,
                                    'status'        => ($newRemaining == 0) ? 1 : 0,
                                    'updated_at'    => now(),
                                ]);
                                
                           
                    
                            $splitCode      = $childCode;
                            $currentSplitId = $existingSameAssembly->id;
                    
                        } else {
                    
                            // Insert new A split
                            $childSplitId = DB::table('order_splits')->insertGetId([
                                'order_id'      => $order->id,
                                'from_stage_id' => $fromStage->id,
                                'to_stage_id'   => $toStage->id,
                                'assigned_qty'  => $assignedQty,
                                'qty'           => $assignedQty,
                                'remaining_qty' => 0,
                                'action_by'     => $user->id,
                                'remarks'       => $request->remarks ?? '',
                                'isComplete'    => 1,
                                'currentStage'  => $fromStage->name,
                                'split_code'    => $childCode,
                                'status'        => 1,
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ]);
                    
                            $splitCode      = $childCode;
                            $currentSplitId = $childSplitId;
                        }
                    }
                     else {
                
                        \Log::info("⚪ Default split used", [
                            'order_id'     => $order->id,
                            'base_code'    => $baseCode,
                            'base_code'    => $baseCode,
                            '$roleName'    => $roleName,
                            'assigned_qty' => $assignedQty
                        ]);
                
                        $splitCode = $baseCode;
                    }
                }
            }

            // -------------------------------------------
            // INSERT OR UPDATE SPLIT
            // -------------------------------------------
            if (!$handledCustomSplit) {
                if ($activeSplit) {
                    // Update existing split
                    $newAssigned  = $activeSplit->assigned_qty + $assignedQty;
                    $newRemaining = max($activeSplit->qty - $newAssigned, 0);
        
                    DB::table('order_splits')->where('id', $activeSplit->id)->update([
                        'assigned_qty'  => $newAssigned,
                        'remaining_qty' => $newRemaining,
                        'isComplete'    => ($newRemaining == 0) ? 1 : 0,
                        'status'        => ($newRemaining == 0) ? 1 : 0,
                        'updated_at'    => now(),
                    ]);
        
                    $currentSplitId = $activeSplit->id;
        
                } else {
                    // Insert new split
                    $remaining = max($totalQty - $assignedQty, 0);
        
                    $currentSplitId = DB::table('order_splits')->insertGetId([
                        'order_id'      => $order->id,
                        'from_stage_id' => $fromStage->id,
                        'to_stage_id'   => $toStage->id,
                        'assigned_qty'  => $assignedQty,
                        'qty'           => $totalQty,
                        'remaining_qty' => $remaining,
                        'action_by'     => $user->id,
                        'remarks'       => $request->remarks ?? '',
                        'isComplete'    => ($remaining == 0) ? 1 : 0,
                        'currentStage'  => $fromStage->name,
                        'split_code'    => $splitCode,
                        'status'        => ($remaining == 0) ? 1 : 0,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
    
            // -------------------------------------------
            // CREATE NEXT STAGE RECORD IF NOT EXISTING
            // -------------------------------------------
            
            
            $nextExists = DB::table('order_splits')
                ->where('order_id', $order->id)
                ->where('currentStage', $toStage->name)
                ->where('split_code', $splitCode)
                ->where('isComplete', 0)
                ->first();
    
            if (!$nextExists) {
                DB::table('order_splits')->insert([
                    'order_id'      => $order->id,
                    'from_stage_id' => $fromStage->id,
                    'to_stage_id'   => $toStage->id,
                    'assigned_qty'  => 0,
                    'qty'           => $assignedQty,
                    'remaining_qty' => $assignedQty,
                    'action_by'     => $user->id,
                    'remarks'       => $request->remarks ?? '',
                    'isComplete'    => 0,
                    'currentStage'  => $toStage->name,
                    'split_code'    => $splitCode,
                    'status'        => 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
    
            // -------------------------------------------
            // UPDATE ORDER (do NOT update splitted_code for Assembly)
            // -------------------------------------------
            $existingStages = [];
            if (!empty($order->current_stage_id)) {
                $decoded = json_decode($order->current_stage_id, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $existingStages = $decoded;
                } else {
                    $existingStages = [(int)$order->current_stage_id];
                }
            }
    
            if (!in_array($toStage->id, $existingStages)) {
                $existingStages[] = $toStage->id;
            }
    
            $orderUpdate = [
                'split_id'         => $currentSplitId,
                'current_stage_id' => json_encode($existingStages),
                'updated_at'       => now(),
            ];
    
            $order->update($orderUpdate);
    
            // Log stage action
            DB::table('order_stage_logs')->insert([
                'order_id'   => $order->id,
                'stage_id'   => $toStage->id,
                'action_by'  => $user->id,
                'remarks'    => $request->remarks ?? '',
                'status'     => 'In Progress',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    
            DB::commit();
    
            return response()->json([
                'Resp_code' => true,
                'Resp_desc' => 'Order assigned successfully.',
            ]);
    
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'Resp_code' => false,
                'Resp_desc' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function addRemarks(Request $request)
    {
        $request->validate([
            'orderId' => 'required|integer|exists:orders,id',
        ]);

        try {
            $order = Order::find($request->orderId);
            $order->remarks = $request->remarks ?? '';
            $order->save();

            return response()->json([
                'Resp_code' => 'true',
                'Resp_desc' => 'add successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'Resp_code' => 'false',
                'Resp_desc' => 'Failed to update order: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    public function clean($value) 
    {
        // removes NBSP + multiple spaces + trims
        return trim(preg_replace('/\s+/u', ' ', $value ?? ''));
    }
  
    
    public function customerSupport(Request $request)
    {
        try {
 
            /* =====================================================
         | 1. BASE QUERY (FILTERS)
         ===================================================== */
            $query = Order::query();
          	$query->whereNotExists(function ($q) {
              $q->select(DB::raw(1))
                  ->from('order_splits as os')
                  ->whereColumn('os.order_id', 'orders.id')
                  ->where('os.currentStage', 'Packaging')
                  ->where('os.remaining_qty', '>', 0);
          	});
            if ($request->filled('search')) {
                $search = $request->search;
            
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhere('customer_po_no', 'like', "%{$search}%")
                      ->orWhere('soa_no', 'like', "%{$search}%")
                      ->orWhere('code_no', 'like', "%{$search}%")
                      ->orWhere('product', 'like', "%{$search}%")
                      ->orWhere('party_name', 'like', "%{$search}%");
                });
            }

 
            if ($request->filled('assembly_no')) {
                $query->where('assembly_no', 'like', '%' . $request->assembly_no . '%');
            }
 
            $fullQuery = clone $query;
            $isSearch = $request->filled('search') || $request->filled('assembly_no');

 
            /* =====================================================
             | 2. STAGE FILTER
             ===================================================== */
            $selectedStage = $request->stage;
 
            if ($selectedStage && strtolower($selectedStage) !== 'planning') {
 
                $splitOrderIds = DB::table('order_splits')
                    ->distinct()
                    ->pluck('order_id')
                    ->toArray();
 
                $splitIds = DB::table('order_splits')
                    ->where('currentStage', $selectedStage)
                    ->where('remaining_qty', '>', 0)
                    ->pluck('order_id')
                    ->toArray();
 
                if ($selectedStage === 'Material Issue') {
                    $defaultIds = Order::whereNotIn('id', $splitOrderIds)
                        ->where(function ($q) {
                            $q->whereNull('finished_valve')
                                ->orWhere('finished_valve', '');
                        })
                        ->pluck('id')
                        ->toArray();
                } elseif ($selectedStage === 'SVS') {
                    $defaultIds = Order::whereNotIn('id', $splitOrderIds)
                        ->whereRaw('LOWER(finished_valve) = ?', ['yes'])
                        ->pluck('id')
                        ->toArray();
                } else {
                    $defaultIds = [];
                }
 
                $filteredIds = array_unique(array_merge($splitIds, $defaultIds));
 
                if (!empty($filteredIds)) {
                    $query->whereIn('id', $filteredIds);
                    $fullQuery->whereIn('id', $filteredIds);
                } else {
                    $query->whereRaw('1=0');
                    $fullQuery->whereRaw('1=0');
                }
            }
 
            /* =====================================================
             | 3. PAGINATION
             ===================================================== */
           

            Paginator::currentPageResolver(function () use ($request) {
                return (int) $request->input('current_page', 1);
            });
            
            if ($isSearch) {
                $orders = $query->orderBy('id')->get();
                $allOrders = $orders;
            } else {
                $orders = $query
                    ->orderBy('id')
                    ->paginate($request->input('per_page', 100));
            
                $allOrders = $fullQuery->orderBy('id')->get();
            }


            /* =====================================================
             | 4. PRELOAD ALL SPLITS (ONCE)
             ===================================================== */
            $allOrderIds = $allOrders->pluck('id');
 
            $allSplits = DB::table('order_splits')
                ->whereIn('order_id', $allOrderIds)
                ->get()
                ->groupBy('order_id');
 
            /* =====================================================
         | 5. COUNTS
         ===================================================== */
            $totalOrders  = $allOrders->count();
            $totalQty     = $allOrders->sum('qty');
            $urgentOrders = $allOrders->where('urgent', 1)->count();
 
            $completedQty = 0;
            $completedOrders = 0;
 
            foreach ($allOrders as $o) {
 
                $splits = $allSplits->get($o->id, collect());
                $splitsByStage = $splits->groupBy('currentStage');
 
                $dispatchAssigned = $splitsByStage
                    ->get('Dispatch', collect())
                    ->sum('assigned_qty');
 
                $nonDispatchExists = $splits
                    ->where('currentStage', '!=', 'Dispatch')
                    ->where('remaining_qty', '>', 0)
                    ->isNotEmpty();
 
                if ($dispatchAssigned > 0) {
                    $completedQty += $dispatchAssigned;
                }
 
                if ($dispatchAssigned == $o->qty && !$nonDispatchExists) {
                    $completedOrders++;
                }
            }
 
            /* =====================================================
         | 6. STAGE LIST & RULES
         ===================================================== */
            $stageList = Stage::pluck('name')->toArray();
 
            $beforeSVS = [
                "Material Issue",
                "Semi QC",
                "Phosphating QC",
                "Assembly A",
                "Assembly B",
                "Assembly C",
                "Assembly D",
                "Testing1",
                "Testing2"
            ];
 
            /* =====================================================
         | 7. BUILD STAGE PROGRESS
         ===================================================== */
            $final = [];
 
            foreach ($orders as $o) {
 
                $splits = $allSplits->get($o->id, collect());
                $splitsByStage = $splits->groupBy('currentStage');
 
                $orderQty = (int) $o->qty;
                $assembly = strtoupper(trim($o->assembly_no));
                $fv = strtolower(trim($o->finished_valve));
 
                $dispatchAssigned = $splitsByStage
                    ->get('Dispatch', collect())
                    ->sum('assigned_qty');
 
                $dispatchRemaining = $splitsByStage
                    ->get('Dispatch', collect())
                    ->sum('remaining_qty');
 
                $dispatchRunning = ($dispatchAssigned > 0 || $dispatchRemaining > 0);
 
                $tpiActive = $splitsByStage
                    ->get('TPI', collect())
                    ->sum('assigned_qty') > 0;
 
                $progress = [];
 
                foreach ($stageList as $label) {
 
                    if ($label === 'Planning') {
                        $progress[$label] = 'ok';
                        continue;
                    }
                  
                    if ($label === 'SVS' && $fv !== 'yes') {
                        $progress[$label] = 'Skip';
                        continue;
                    }
 
                    if ($fv === 'yes' && in_array($label, $beforeSVS)) {
                        $progress[$label] = '-';
                        continue;
                    }
 
                    if (str_starts_with($label, 'Assembly')) {
                        if (strtoupper(substr($label, -1)) !== $assembly) {
                            $progress[$label] = '-';
                            continue;
                        }
                    }
 
                    if (in_array($assembly, ['A', 'B', 'C']) && in_array($label, ['Testing2', 'Marking2', 'PDI2'])) {
                        $progress[$label] = '-';
                        continue;
                    }
 
                    if ($assembly === 'D' && in_array($label, ['Testing1', 'Marking1', 'PDI1'])) {
                        $progress[$label] = '-';
                        continue;
                    }
 
                    if ($label === 'TPI' && !$tpiActive) {
                        $progress[$label] = 'Skip';
                        continue;
                    }
 
                    if ($dispatchRunning && in_array($label, ['Dispatch', 'SVS'])) {
                        $progress[$label] = 'ok';
                        continue;
                    }
 
                    $stageAssigned = $splitsByStage
                        ->get($label, collect())
                        ->sum('assigned_qty');
 
                    $processed = $dispatchAssigned + $stageAssigned;
 
                    if ($processed == 0) {
                        $progress[$label] = 'pending';
                    } elseif ($processed >= $orderQty) {
                        $progress[$label] = 'ok';
                    } else {
                        $progress[$label] = 'in_progress';
                    }
                }
 
                $o->qty_executed = $dispatchAssigned;
                $o->qty_pending  = max($orderQty - $dispatchAssigned, 0);
                $o->stage_progress = $progress;
 
                $final[] = $o;
            }
 
            /* =====================================================
         | 8. RESPONSE
         ===================================================== */
            return response()->json([
                'Resp_code' => true,
                'Resp_desc' => 'Orders fetched successfully',
                'counts' => [
                    'totalOrders'    => $totalOrders,
                    'totalQty'       => $totalQty,
                    'completeOrders' => $completedQty,
                    'urgentOrders'   => $urgentOrders,
                    'pendingQty'     => $totalQty - $completedQty,
                ],
                'pagination' => $isSearch ? null : [
                    'current_page' => $orders->currentPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                    'last_page'    => $orders->lastPage(),
                ],

                'data' => collect($final)->values()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'Resp_code' => false,
                'Resp_desc' => 'Something went wrong',
                'error'     => $e->getMessage()
            ], 500);
        }
    }

    // public function orderCounts(Request $request)
    //     {
    //     try {
    
    //         $today = now();
    
    //         // ===== CURRENT MONTH =====
    //         $currentStart = $today->copy()->startOfMonth();
    //         $currentEnd   = $today->copy()->endOfMonth();
    
    //         // ===== PREVIOUS MONTH =====
    //         $previousStart = $today->copy()->subMonth()->startOfMonth();
    //         $previousEnd   = $today->copy()->subMonth()->endOfMonth();
    
    //         // ===== BASE QUERY =====
    //         $baseQuery = Order::query();
    
    //         if ($request->assemblyDate) {
    //             $baseQuery->where('assembly_date', $request->assemblyDate);
    //         }
    
    //         // ====================================================
    //         // 🔹 FULL TABLE TOTAL / COMPLETED / INPROGRESS
    //         // ====================================================
    
    //         $allOrders = Order::select('id','qty','finished_valve')->get();
    //         $allOrderIds = $allOrders->pluck('id');
    
    //         $allSplitOrders = SplitOrder::whereIn('order_id', $allOrderIds)
    //             ->whereNull('ocl_no')
    //             ->select('order_id','currentStage','remaining_qty')
    //             ->get()
    //             ->groupBy('order_id');
    
    //         $totalOrdersAll = $allOrders->count();
    //         $completedAll = 0;
    //         $inProgressAll = 0;
    
    //         foreach ($allOrders as $order) {
    
    //             $orderId = $order->id;
    //             $qty = (int)$order->qty;
    
    //             if ($allSplitOrders->has($orderId)) {
    
    //                 $splits = $allSplitOrders[$orderId];
    
    //                 $dispatchQty = $splits
    //                     ->where('currentStage','Packaging')
    //                     ->sum('remaining_qty');
    
    //                 $otherPending = $splits
    //                     ->whereNotIn('currentStage',['Dispatch','Packaging'])
    //                     ->where('remaining_qty','>',0)
    //                     ->sum('remaining_qty');
    
    //                 if ($dispatchQty == $qty && $otherPending == 0) {
    //                     $completedAll++;
    //                 } else {
    //                     $inProgressAll++;
    //                 }
    
    //             } else {
    
    //                 if (strtolower(trim($order->finished_valve)) == 'yes') {
    //                     $completedAll++;
    //                 } else {
    //                     $inProgressAll++;
    //                 }
    //             }
    //         }
    
    //         // ====================================================
    //         // 🔹 CURRENT MONTH ORDERS (FOR STAGE + % ONLY)
    //         // ====================================================
    
    //         $currentOrders = (clone $baseQuery)
    //             ->whereBetween('created_at', [$currentStart, $currentEnd])
    //             ->select('id', 'qty', 'finished_valve')
    //             ->get();
    
    //         $currentOrderIds = $currentOrders->pluck('id');
    
    //         $splitOrders = SplitOrder::whereIn('order_id', $currentOrderIds)
    //             ->whereNull('ocl_no')
    //             ->select('order_id', 'currentStage', 'remaining_qty')
    //             ->get()
    //             ->groupBy('order_id');
    
    //         $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])
    //             ->select('id','qty','finished_valve')
    //             ->get();
    
    //         $previousStats = $this->calculateMonthStats($previousOrders);
    
    //         // ===== STAGE MAP =====
    //         $stageMap = [
    //             'Material Issue' => 'pendingMaterialIssue',
    //             'Semi QC' => 'pendingSemiQC',
    //             'Phosphating QC' => 'pendingPhosphatingQC',
    //             'SVS' => 'pendingSVS',
    //             'Testing1' => 'pendingTesting1',
    //             'Testing2' => 'pendingTesting2',
    //             'Marking1' => 'pendingMarking1',
    //             'Marking2' => 'pendingMarking2',
    //             'PDI1' => 'pendingPDI1',
    //             'PDI2' => 'pendingPDI2',
    //             'TPI' => 'pendingTPI',
    //             'Assembly A' => 'pendingAssemblyA',
    //             'Assembly B' => 'pendingAssemblyB',
    //             'Assembly C' => 'pendingAssemblyC',
    //             'Assembly D' => 'pendingAssemblyD',
    //             'Dispatch' => 'pendingDispatch',
    //             'Packaging' => 'pendingPackaging',
    //         ];
    
    //         $counts = [
    //             'totalOrders' => $totalOrdersAll,
    //             'completed'   => $completedAll,
    //             'inProgress'  => $inProgressAll,
    //         ];
    
    //         foreach ($stageMap as $value) {
    //             $counts[$value] = 0;
    //         }
    
    //         // ===== CURRENT MONTH LOOP (NO TOUCHING TOTAL COUNTS)
    //         $currentCompleted = 0;
    //         $currentInProgress = 0;
    
    //         foreach ($currentOrders as $order) {
    
    //             $orderId = $order->id;
    //             $qty = (int)$order->qty;
    
    //             if ($splitOrders->has($orderId)) {
    
    //                 $splits = $splitOrders[$orderId];
    
    //                 $dispatchQty = $splits->where('currentStage','Packaging')->sum('remaining_qty');
    
    //                 $otherPending = $splits
    //                     ->whereNotIn('currentStage',['Dispatch','Packaging'])
    //                     ->where('remaining_qty','>',0)
    //                     ->sum('remaining_qty');
    
    //                 if ($dispatchQty == $qty && $otherPending == 0) {
    //                     $currentCompleted++;
    //                 } else {
    //                     $currentInProgress++;
    //                 }
    
    //                 foreach ($splits as $split) {
    
    //                     $remaining = (int)$split->remaining_qty;
    //                     if ($remaining <= 0) continue;
    
    //                     $stage = trim($split->currentStage);
    
    //                     if (isset($stageMap[$stage])) {
    //                         $counts[$stageMap[$stage]] += $remaining;
    //                     }
    //                 }
    
    //             } else {
    
    //                 if (strtolower(trim($order->finished_valve)) == 'yes') {
    //                     $currentCompleted++;
    //                     $counts['pendingSVS'] += $qty;
    //                 } else {
    //                     $currentInProgress++;
    //                     $counts['pendingMaterialIssue'] += $qty;
    //                 }
    //             }
    //         }
    
    //         // ===== PERCENTAGE =====
    //         $counts['totalOrdersCompare'] = $this->calculatePercentage(
    //             $currentOrders->count(),
    //             $previousStats['total']
    //         );
    
    //         $counts['completedCompare'] = $this->calculatePercentage(
    //             $currentCompleted,
    //             $previousStats['completed']
    //         );
    
    //         $counts['inProgressCompare'] = $this->calculatePercentage(
    //             $currentInProgress,
    //             $previousStats['inProgress']
    //         );
    
    //         return response()->json([
    //             'Resp_code' => true,
    //             'Resp_desc' => 'Counts fetched successfully.',
    //             'counts' => $counts
    //         ]);
    
    //     } catch (\Exception $e) {
    
    //         \Log::error($e->getMessage());
    
    //         return response()->json([
    //             'Resp_code' => false,
    //             'Resp_desc' => 'Error while fetching counts.'
    //         ], 500);
    //     }
    // }


    public function orderCounts(Request $request)
    {
        try {
    
            $today = now();
    
            $currentStart  = $today->copy()->startOfMonth();
            $currentEnd    = $today->copy()->endOfMonth();
            $previousStart = $today->copy()->subMonth()->startOfMonth();
            $previousEnd   = $today->copy()->subMonth()->endOfMonth();
    
            // ====================================================
            // 🔹 FULL TABLE ORDERS
            // ====================================================
    
            $allOrders = Order::select('id','qty','finished_valve')->get();
            $allOrderIds = $allOrders->pluck('id');
    
            $allSplitOrders = SplitOrder::whereIn('order_id', $allOrderIds)
                ->whereNull('ocl_no')
                ->select('order_id','currentStage','remaining_qty')
                ->get()
                ->groupBy('order_id');
    
            $totalOrdersAll = $allOrders->count();
            $completedAll = 0;
            $inProgressAll = 0;
    
            foreach ($allOrders as $order) {
    
                $orderId = $order->id;
                $qty = (int)$order->qty;
    
                if ($allSplitOrders->has($orderId)) {
    
                    $splits = $allSplitOrders[$orderId];
    
                    $dispatchQty = $splits
                        ->where('currentStage','Packaging')
                        ->sum('remaining_qty');
    
                    $otherPending = $splits
                        ->whereNotIn('currentStage',['Dispatch','Packaging'])
                        ->where('remaining_qty','>',0)
                        ->sum('remaining_qty');
    
                    if ($dispatchQty == $qty && $otherPending == 0) {
                        $completedAll++;
                    } else {
                        $inProgressAll++;
                    }
    
                } else {
    
                    if (strtolower(trim($order->finished_valve)) == 'yes') {
                        $completedAll++;
                    } else {
                        $inProgressAll++;
                    }
                }
            }
    
            // ====================================================
            // 🔹 STAGE MAP
            // ====================================================
    
            $stageMap = [
                'Material Issue' => 'pendingMaterialIssue',
                'Semi QC' => 'pendingSemiQC',
                'Phosphating QC' => 'pendingPhosphatingQC',
                'SVS' => 'pendingSVS',
                'Testing1' => 'pendingTesting1',
                'Testing2' => 'pendingTesting2',
                'Marking1' => 'pendingMarking1',
                'Marking2' => 'pendingMarking2',
                'PDI1' => 'pendingPDI1',
                'PDI2' => 'pendingPDI2',
                'TPI' => 'pendingTPI',
                'Assembly A' => 'pendingAssemblyA',
                'Assembly B' => 'pendingAssemblyB',
                'Assembly C' => 'pendingAssemblyC',
                'Assembly D' => 'pendingAssemblyD',
                'Dispatch' => 'pendingDispatch',
                'Packaging' => 'pendingPackaging',
            ];
    
            $counts = [
                'totalOrders' => $totalOrdersAll,
                'completed'   => $completedAll,
                'inProgress'  => $inProgressAll,
            ];
    
            foreach ($stageMap as $value) {
                $counts[$value] = 0;
            }
    
            // ====================================================
            // 🔹 FULL TABLE STAGE LOOP
            // ====================================================
    
            foreach ($allOrders as $order) {
    
                $orderId = $order->id;
                $qty = (int)$order->qty;
    
                if ($allSplitOrders->has($orderId)) {
    
                    $splits = $allSplitOrders[$orderId];
    
                    foreach ($splits as $split) {
    
                        $remaining = (int)$split->remaining_qty;
                        if ($remaining <= 0) continue;
    
                        $stage = trim($split->currentStage);
    
                        if (isset($stageMap[$stage])) {
                            $counts[$stageMap[$stage]] += $remaining;
                        }
                    }
    
                } else {
    
                    if (strtolower(trim($order->finished_valve)) == 'yes') {
                        $counts['pendingSVS'] += $qty;
                    } else {
                        $counts['pendingMaterialIssue'] += $qty;
                    }
                }
            }
    
            // ====================================================
            // 🔹 MONTH COMPARISON (PERCENTAGE PART RESTORED)
            // ====================================================
    
            $currentOrders = Order::whereBetween('created_at', [$currentStart, $currentEnd])
                ->select('id','qty','finished_valve')
                ->get();
    
            $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])
                ->select('id','qty','finished_valve')
                ->get();
    
            $previousStats = $this->calculateMonthStats($previousOrders);
            $currentStats  = $this->calculateMonthStats($currentOrders);
    
            $counts['totalOrdersCompare'] = $this->calculatePercentage(
                $currentStats['total'],
                $previousStats['total']
            );
    
            $counts['completedCompare'] = $this->calculatePercentage(
                $currentStats['completed'],
                $previousStats['completed']
            );
    
            $counts['inProgressCompare'] = $this->calculatePercentage(
                $currentStats['inProgress'],
                $previousStats['inProgress']
            );
    
            return response()->json([
                'Resp_code' => true,
                'Resp_desc' => 'Counts fetched successfully.',
                'counts' => $counts
            ]);
    
        } catch (\Exception $e) {
    
            \Log::error($e->getMessage());
    
            return response()->json([
                'Resp_code' => false,
                'Resp_desc' => 'Error while fetching counts.'
            ], 500);
        }
    }

   private function calculatePercentage($current, $previous)
{
    if ($previous == 0 && $current == 0) {
        return "0%";
    }

    if ($previous == 0) {
        return "100%";
    }

    $change = $current - $previous;
    $percentage = ($change / $previous) * 100;

    // Limit between -100% and 100%
    if ($percentage > 100) {
        $percentage = 100;
    }

    if ($percentage < -100) {
        $percentage = -100;
    }

    return round($percentage) . "%";
}
    
    private function calculateMonthStats($orders)
    {
    $orderIds = $orders->pluck('id');

    $splitOrders = SplitOrder::whereIn('order_id', $orderIds)
        ->whereNull('ocl_no')
        ->select('order_id','currentStage','remaining_qty')
        ->get()
        ->groupBy('order_id');

    $total = $orders->count();
    $completed = 0;
    $inProgress = 0;

    foreach ($orders as $order) {

        $orderId = $order->id;
        $qty = (int) $order->qty;

        if ($splitOrders->has($orderId)) {

            $splits = $splitOrders[$orderId];

            $dispatchQty = $splits
                ->where('currentStage','Packaging')
                ->sum('remaining_qty');

            $otherPending = $splits
                ->whereNotIn('currentStage',['Dispatch','Packaging'])
                ->where('remaining_qty','>',0)
                ->sum('remaining_qty');

            if ($dispatchQty == $qty && $otherPending == 0) {
                $completed++;
            } else {
                $inProgress++;
            }

        } else {

            if (strtolower(trim($order->finished_valve)) == 'yes') {
                $completed++;
            } else {
                $inProgress++;
            }
        }
    }

    return [
        'total' => $total,
        'completed' => $completed,
        'inProgress' => $inProgress
    ];
}

    public function updateDeliveryDate(Request $request)
    {
        try {
    
            // Validate request
            $request->validate([
                'orderId' => 'required|exists:orders,id',
                'date'    => 'required'
            ]);
    
            $orderId = $request->orderId;
            $date = $request->date;
            
            // Update order with given fields
            Order::where('id', $orderId)->update([
                'deliveryDate' => $date, // replace with the actual column you want to update
            ]);
                
            return response()->json([
                'Resp_code' => true,
                'Resp_desc' => 'Order updated successfully',
            ]);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'Resp_code' => false,
                'Resp_desc' => 'Something went wrong',
                'error'     => $e->getMessage()
            ], 500);
        }
    }
    
    public function orderHistory(Request $request)
    {
        try {
            $request->validate([
                'orderId' => 'required|exists:orders,id'
            ]);
    
            $order = Order::findOrFail($request->orderId);
    
            // Fetch all splits for this order
            $splits = SplitOrder::where('order_id', $order->id)->orderBy('id', 'asc')->get();
    
            if ($splits->isEmpty()) {
                return response()->json([
                    'Resp_code' => true,
                    'Resp_desc' => 'Order history fetched successfully',
                    'order' => $order,
                    'splits' => []
                ]);
            }
    

            // =======================================
            // MAIN ORDER — PLANNING ENTRY (ONE TIME)
            // =======================================
            $grouped = [];
            
            // Calculate duration from orders table
            $entered = $order->created_at;
            $exited  = $order->updated_at;
            
            $duration = null;
            if ($entered && $exited) {
                $duration = Carbon::parse($entered)->diffForHumans(
                    Carbon::parse($exited),
                    [
                        'parts'  => 3,
                        'short'  => true,
                        'syntax' => CarbonInterface::DIFF_ABSOLUTE
                    ]
                );
            }
            
            // Use order number or fixed key for grouping
            $grouped['ORDER'][] = [
                'split_code'    => $order->unique_code ?? '-',
                'qty'           => $order->qty,
                'assigned_qty'  => 0,
                'remaining_qty' => $order->qty,
                'entered'       => $entered ? Carbon::parse($entered)->format('d-m-Y h:i A') : null,
                // 'exited'        => $exited ? Carbon::parse($exited)->format('d-m-Y h:i A') : null,
                // 'duration'      => $duration,
                'currentStage'  => 'Planning',
            ];


            foreach ($splits as $row) {
            
                // Extract the base M-code
                $baseCode = $this->getBaseMCode($row->split_code);
            
                $entered = $row->created_at;
                $exited  = $row->updated_at;
            
                $duration = null;
            
                if ($entered && $exited) {
                    $duration = Carbon::parse($entered)->diffForHumans(
                        Carbon::parse($exited),
                        [
                            'parts'  => 3,
                            'short'  => true,
                            'syntax' => CarbonInterface::DIFF_ABSOLUTE
                        ]
                    );
                }
            
                $grouped[$baseCode][] = [
                    'split_code'    => $row->split_code,
                    'qty'           => $row->qty,
                    'assigned_qty'  => ($row->currentStage == 'Packaging') ? '-' : $row->assigned_qty,
                    'remaining_qty' => $row->remaining_qty,
                    'entered'       => $entered ? Carbon::parse($entered)->format('d-m-Y h:i A') : null,
                    'exited'        => $exited ? Carbon::parse($exited)->format('d-m-Y h:i A') : null,
                    'duration'      => $duration,
                    'currentStage'  => $row->currentStage,
                ];
            }

           
            return response()->json([
                'Resp_code' => true,
                'Resp_desc' => 'Order history fetched successfully',
                'order'     => $order,
                'splits'    => $grouped
            ]);
    
    
        } catch (\Exception $e) {
            return response()->json([
                'Resp_code' => false,
                'Resp_desc' => 'Something went wrong',
                'error'     => $e->getMessage()
            ], 500);
        }
    }
    
    private function getBaseMCode($splitCode)
    {
        // Matches: <anything>-M<number>
        if (preg_match('/^(.*-M\d+)/', $splitCode, $m)) {
            return $m[1];
        }
    
        // If no M found → return whole code as fallback
        return $splitCode;
    }
    
    public function dispatchToPackaging(Request $request)
    {
        $request->validate([
            'split_id'     => 'required|exists:order_splits,id',
            'order_id'     => 'required|exists:orders,id',
            'currentSteps'=> 'required|string',
            'nextSteps'   => 'required|string|in:Packaging',
            'ocl_no'      => 'required|string'
        ]);

        DB::beginTransaction();

        try {

            $user     = Auth::user();

            $fromStage = Stage::where('name', $request->currentSteps)->first();
            $toStage   = Stage::where('name', $request->nextSteps)->first();
            
            // 1️⃣ Fetch existing split
            $split = SplitOrder::where('id', $request->split_id)->where('order_id', $request->order_id)->first();

            if ($split->remaining_qty <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Remaining quantity already zero'
                ], 422);
            }

            $remainingQty = $split->remaining_qty;

            // 2️⃣ Update existing split
            $split->update([
                'assigned_qty' => $remainingQty,
                'remaining_qty' => 0,
                'isComplete' => 1,
                'status' => 1
            ]);

            // 3️⃣ Create new split for Packaging
            $newSplit = SplitOrder::create([
                'order_id'      => $split->order_id,
                'from_stage_id'      => $fromStage->id,
                'to_stage_id'      => $toStage->id,
                'split_code'    => $split->split_code,   // keep same base code
                'qty'           => $split->qty,
                'assigned_qty'  => 0,
                'remaining_qty' => $remainingQty,
                'currentStage'  => $request->nextSteps,
                'ocl_no'        => $request->ocl_no,
                'split_code'    => $split->split_code,
                 'action_by'     => $user->id,
                'status'        => 0,
                'isComplete'    => 0,
                'is_packaging'  => '1',
                'created_at'    => now(),
                'updated_at'    => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Moved from Dispatch to Packaging successfully',
                'updated_split' => $split,
                'new_split' => $newSplit
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function packagingOrders(Request $request)
    {
        $orders = DB::table('orders as o')
            ->join('order_splits as s', 's.order_id', '=', 'o.id')
            ->where('s.currentStage', 'Packaging')
            ->where('s.remaining_qty', '>', 0)
            ->select([
                // ---- ORDER TABLE ----
                'o.id',
                'o.order_no',
                'o.assembly_no',
                'o.soa_no',
                'o.soa_sr_no',
                'o.assembly_date',
                'o.unique_code',
                'o.party_name',
                'o.customer_po_no',
                'o.code_no',
                'o.product',
                'o.qty',
                'o.po_qty',
                'o.qty_executed',
                'o.qty_pending',
                'o.finished_valve',
                'o.gm_logo',
                'o.name_plate',
                'o.special_notes',
                'o.product_spc1',
                'o.product_spc2',
                'o.product_spc3',
                'o.inspection',
                'o.painting',
                'o.remarks',
                'o.urgent',
                'o.current_stage_id',
                'o.status',
                'o.created_by',
                'o.created_at',
                'o.updated_at',
              	'o.deliveryDT',

                // ---- SPLIT TABLE ----
                's.id as split_id',
                's.split_code',
                's.qty as totalQty',
                's.assigned_qty',
                's.remaining_qty',
                's.ocl_no',
                's.is_packaging'
            ])
            ->orderBy('o.id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Packaging orders fetched successfully',
            'data' => $orders
        ]);
    }
    
    public function changeToPackaging(Request $request)
    {
        $request->validate([
            'split_id'     => 'required|exists:order_splits,id',
            'packaging'     => 'required',
        ]);

        DB::beginTransaction();

        try {

            // 1️⃣ Fetch existing split
            $split = SplitOrder::where('id', $request->split_id)->first();

            if ($split->remaining_qty <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Remaining quantity already zero'
                ], 422);
            }

            $remainingQty = $split->remaining_qty;

            // 2️⃣ Update existing split
            $split->update([
                'is_packaging' => $request->packaging
            ]);

          

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Moved from Dispatch to Packaging successfully',
                'updated_split' => $split,
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
}