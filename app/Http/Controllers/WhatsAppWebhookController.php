<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp webhook messages.
     */
    public function receiveWebhook(Request $request)
    {
        Log::info('Received WhatsApp webhook', $request->all());

        // --- STEP 1: Extract the message and sender information ---
        //
        // The incoming payload from Meta typically looks like:
        // {
        //   "object": "whatsapp_business_account",
        //   "entry": [{
        //       "id": "YOUR_BUSINESS_ACCOUNT_ID",
        //       "changes": [{
        //           "value": {
        //               "messaging_product": "whatsapp",
        //               "metadata": { "phone_number_id": "..." },
        //               "contacts": [ { "wa_id": "SENDER_NUMBER", ... } ],
        //               "messages": [{
        //                   "from": "SENDER_NUMBER",
        //                   "id": "MESSAGE_ID",
        //                   "timestamp": "TIMESTAMP",
        //                   "text": { "body": "MESSAGE TEXT" },
        //                   "type": "text"
        //               }]
        //           },
        //           "field": "messages"
        //       }]
        //   }]
        // }
        //
        // Adjust the extraction below if your payload differs.
        $message = $request->input('entry.0.changes.0.value.messages.0.text.body');
        $from    = $request->input('entry.0.changes.0.value.messages.0.from');

        // If there’s no message, simply return (or handle non-text messages as needed)
        if (!$message || !$from) {
            return response()->json(['status' => 'no valid message found'], 200);
        }

        // --- STEP 2: Call ChatGPT (OpenAI API) with the received message ---
        try {
            $openaiResponse = Http::withToken(env('OPENAI_API_KEY'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-3.5-turbo',
                    'messages'    => [
                        // The system message can be customized as needed
                        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'temperature' => 0.7,
                ]);
        } catch (\Exception $e) {
            Log::error('Error calling OpenAI API: ' . $e->getMessage());
            return response()->json(['status' => 'error calling OpenAI API'], 500);
        }

        if (!$openaiResponse->successful()) {
            Log::error('OpenAI API error', ['response' => $openaiResponse->body()]);
            return response()->json(['status' => 'error from OpenAI API'], 500);
        }

        // Retrieve the ChatGPT reply from the API response
        $reply = data_get($openaiResponse->json(), 'choices.0.message.content');
        if (!$reply) {
            Log::error('No reply from OpenAI API', ['response' => $openaiResponse->json()]);
            return response()->json(['status' => 'no reply from OpenAI'], 500);
        }

        // --- STEP 3: Send the ChatGPT reply back to the WhatsApp user ---
        $whatsappToken  = env('WHATSAPP_TOKEN');
        $phoneId        = env('WHATSAPP_PHONE_ID');
        $whatsappUrl    = "https://graph.facebook.com/v17.0/{$phoneId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $from,
            'type'              => 'text',
            'text'              => [
                'body' => $reply,
            ],
        ];

        try {
            $whatsappResponse = Http::withToken($whatsappToken)
                ->post($whatsappUrl, $payload);
        } catch (\Exception $e) {
            Log::error('Error calling WhatsApp API: ' . $e->getMessage());
            return response()->json(['status' => 'error sending WhatsApp message'], 500);
        }

        if (!$whatsappResponse->successful()) {
            Log::error('WhatsApp API error', ['response' => $whatsappResponse->body()]);
            return response()->json(['status' => 'failed to send message'], 500);
        }

        // Return a success response
        return response()->json(['status' => 'message processed successfully'], 200);
    }
}