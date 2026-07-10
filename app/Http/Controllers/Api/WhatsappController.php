<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;

class WhatsappController extends Controller
{
    public function sendReminder(Request $request)
    {
        $invoice_no = $request->input('invoice_no', '');
        $phone = $request->input('phone', '');
        $name = $request->input('name', '');
        $amount = $request->input('amount', '');
        $due_date = $request->input('due_date', '');
        $template_name = $request->input('template_name', 'payment_reminder');

        if (!empty($invoice_no)) {
            $inv = Invoice::where('invoice_no', $invoice_no)->first();
            if ($inv) {
                $phone = $inv->customer_phone;
                $name = $inv->customer_name;
                $amount = $inv->balance_amount;
                $due_date = $inv->due_date;
            }
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            $phone = "91" . $phone;
        }

        if (empty($phone)) {
            return response()->json([
                "status" => false,
                "message" => "Valid phone number is required."
            ]);
        }

        $formatted_date = "-";
        if (!empty($due_date)) {
            $timestamp = strtotime($due_date);
            if ($timestamp) {
                $formatted_date = date("d-M-Y", $timestamp);
            } else {
                $formatted_date = $due_date;
            }
        }

        $url = "https://graph.facebook.com/v25.0/1116712711534650/messages";
        $access_token = "EAAb2O1UXpDsBR4z1tNDLiDeBt1TZACfakVv0ZAhizbSpWn6REDGZCfPCWE9a98QeVsqz30NpLidAivteuFtZCGgkJL0lSavrgel0qrIwCy7oZB36zatUNHISZBNRZAPMXMY8r45gsLDaCSSmJUEXmleI7sk7S4kzZCw3ZAJULvyxTq1rrfdFOYFq44ZAokZB2Rlon2Ve5vaXUbLJ3PfK9FGwuADwqhNXyL6xkBYk2vhCoEc0fF8ytlOJmtdw7VlJXEnaDRoBZBml7iFeSitpa1zKY4UwNMjG";

        $template_data = [
            "name" => $template_name,
            "language" => [
                "code" => "en_US"
            ]
        ];

        if ($template_name !== 'hello_world') {
            $template_data["components"] = [
                [
                    "type" => "body",
                    "parameters" => [
                        [
                            "type" => "text",
                            "text" => $name
                        ],
                        [
                            "type" => "text",
                            "text" => "₹" . number_format((float)$amount, 2)
                        ],
                        [
                            "type" => "text",
                            "text" => $formatted_date
                        ]
                    ]
                ]
            ];
        }

        $body = [
            "messaging_product" => "whatsapp",
            "to" => $phone,
            "type" => "template",
            "template" => $template_data
        ];

        $fbResponse = Http::withHeaders([
            "Authorization" => "Bearer " . $access_token,
            "Content-Type" => "application/json"
        ])->post($url, $body);

        if ($fbResponse->successful()) {
            return response()->json($fbResponse->json());
        } else {
            return response()->json([
                "status" => false,
                "message" => "WhatsApp API request failed",
                "response" => $fbResponse->json()
            ]);
        }
    }
}
