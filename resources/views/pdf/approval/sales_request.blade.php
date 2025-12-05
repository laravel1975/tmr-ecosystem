<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Approval Document {{ $request->document_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Font Setup (ต้องมั่นใจว่าใน Server มี Font นี้ หรือใช้ Google Fonts) */
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap');

        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #1f2937; /* gray-800 */
        }
        .page-break { page-break-after: always; }

        /* Table Styles */
        th, td { border: 1px solid #e5e7eb; padding: 8px; }
        th { background-color: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body class="p-8">

    {{-- HEADER --}}
    <div class="flex justify-between items-start border-b-2 border-gray-800 pb-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">ใบขออนุมัติ / Approval Request</h1>
            <p class="text-lg text-gray-600 mt-1">{{ $request->workflow->name }}</p>
        </div>
        <div class="text-right">
            <div class="inline-block bg-gray-100 px-3 py-1 rounded">
                <span class="text-xs text-gray-500 block">Document No.</span>
                <span class="text-xl font-mono font-bold text-blue-700">{{ $request->document_number }}</span>
            </div>
            <p class="text-sm text-gray-500 mt-2">
                วันที่: {{ $request->created_at->format('d/m/Y H:i') }}
            </p>
        </div>
    </div>

    {{-- 1. REQUESTER INFO --}}
    <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-100">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-gray-500 text-xs uppercase tracking-wider">ผู้ร้องขอ (Requester)</span>
                <p class="font-bold text-lg">{{ $request->requester->name }}</p>
                <p class="text-sm text-gray-600">{{ $request->requester->email }}</p>
            </div>
            <div class="text-right">
                <span class="text-gray-500 text-xs uppercase tracking-wider">เอกสารอ้างอิง (Ref ID)</span>
                <p class="font-mono font-bold">{{ $request->subject_id }}</p>
                <p class="text-sm text-gray-600">Type: {{ class_basename($request->subject_type) }}</p>
            </div>
        </div>
    </div>

    {{-- 2. DETAILS (PAYLOAD) --}}
    <div class="mb-8">
        <h3 class="text-lg font-bold mb-3 border-b pb-1 flex items-center gap-2">
            📄 รายละเอียดประกอบการพิจารณา
        </h3>
        <table class="w-full text-sm border-collapse">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="w-1/3">รายการ (Parameter)</th>
                    <th>ข้อมูล (Value)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($request->payload_snapshot as $key => $value)
                <tr>
                    <td class="font-medium text-gray-600 capitalize">
                        {{ str_replace('_', ' ', $key) }}
                    </td>
                    <td class="font-mono text-gray-800">
                        {{ is_array($value) ? json_encode($value) : $value }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if(isset($request->payload_snapshot['remark']))
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                <strong>Note:</strong> {{ $request->payload_snapshot['remark'] }}
            </div>
        @endif
    </div>

    {{-- 3. SIGNATURE SECTION (หัวใจสำคัญ) --}}
    <div class="mt-12 break-inside-avoid">
        <h3 class="text-lg font-bold mb-4 border-b pb-1">
            ✍️ บันทึกการอนุมัติ (Authorization Record)
        </h3>

        <table class="w-full text-sm text-center border-collapse">
            <thead>
                <tr class="bg-gray-100 text-gray-700">
                    <th class="w-12">Step</th>
                    <th class="w-32">ตำแหน่ง (Role)</th>
                    <th class="w-24">สถานะ</th>
                    <th class="w-40">ลงชื่อ (Signature)</th>
                    <th>ความเห็น (Comment)</th>
                    <th class="w-32">วันที่</th>
                </tr>
            </thead>
            <tbody>
                @foreach($request->workflow->steps as $step)
                    @php
                        // หา Action ที่เกิดขึ้นใน Step นี้ (ถ้ามี)
                        // Logic: หา action ที่ user มี role ตรงกับ step หรือ เป็นคนกดอนุมัติในลำดับนี้
                        // (แบบง่าย: แมปด้วย Step Order ถ้าคุณเก็บ current_step_order ไว้ตอน approve)
                        // แต่ในที่นี้เราจะ loop หา Action ล่าสุดที่ match กัน

                        $action = $request->actions->first(function($act) use ($step) {
                            // TODO: ใน Production ควรเก็บ step_id ใน table actions เพื่อความแม่นยำ
                            // เบื้องต้นเช็คจาก role หรือลำดับเวลาแทนได้
                            return true; // (Demo: ให้แสดงไปก่อน หรือปรับ Logic ตาม Business จริง)
                        });

                        // Hack สำหรับ Demo: ถ้ามี Action ที่ลำดับตรงกับ Step ให้ดึงมาแสดง
                        $action = $request->actions->skip($step->order - 1)->first();
                    @endphp

                    <tr class="align-top">
                        <td class="py-4">{{ $step->order }}</td>
                        <td class="py-4 font-semibold text-gray-700">{{ $step->approver_role }}</td>

                        <td class="py-4">
                            @if($action)
                                @if($action->action == 'approve')
                                    <span class="text-green-600 font-bold bg-green-50 px-2 py-1 rounded">APPROVED</span>
                                @elseif($action->action == 'reject')
                                    <span class="text-red-600 font-bold bg-red-50 px-2 py-1 rounded">REJECTED</span>
                                @endif
                            @else
                                <span class="text-gray-400 italic">Pending...</span>
                            @endif
                        </td>

                        <td class="py-2">
                            @if($action && $action->actor)
                                <div class="flex flex-col items-center justify-center h-full">
                                    {{-- 🔥 แสดงลายเซ็น (ถ้ามีไฟล์) --}}
                                    @if($action->actor->employeeProfile && $action->actor->employeeProfile->signature_path)
                                        <img src="{{ storage_path('app/public/' . $action->actor->employeeProfile->signature_path) }}"
                                             class="h-12 w-auto object-contain mb-1"
                                             alt="Signature">
                                    @else
                                        {{-- ถ้าไม่มีไฟล์ลายเซ็น ให้แสดงชื่อตัวบรรจงในกล่อง --}}
                                        <div class="h-10 flex items-end justify-center text-xs text-gray-400 italic mb-1">
                                            (Signed Digital)
                                        </div>
                                    @endif

                                    <div class="text-xs font-bold border-t border-gray-300 w-3/4 pt-1 mt-1">
                                        {{ $action->actor->name }}
                                    </div>
                                </div>
                            @else
                                <div class="h-16"></div>
                            @endif
                        </td>

                        <td class="py-4 text-left text-gray-600">
                            {{ $action ? $action->comment : '-' }}
                        </td>

                        <td class="py-4 text-gray-500">
                            {{ $action ? $action->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- FOOTER --}}
    <div class="fixed bottom-0 left-0 w-full text-center text-xs text-gray-400 py-4 border-t">
        <p>เอกสารนี้ถูกสร้างโดยระบบ TMR ecoSystem | Ref: {{ $request->id }} | Printed: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

</body>
</html>
