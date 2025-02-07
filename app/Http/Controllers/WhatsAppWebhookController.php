<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{

        /**
     * Verify the webhook by checking the verify token.
     */
    public function verify(Request $request)
    {
        $verifyToken = $request->query('hub_verify_token');
        $challenge   = $request->query('hub_challenge');

        if ($verifyToken && $verifyToken === env('WHATSAPP_VERIFY_TOKEN')) {
            return response($challenge, 200);
        }

        return response('Invalid Verify Token', 403);
    }

    /**
     * Handle incoming WhatsApp messages.
     *  - Extract the message from the incoming webhook payload.
     *  - Forward the message to ChatGPT.
     *  - Send the ChatGPT reply back via the Meta WhatsApp API.
     */
    public function handleMessage(Request $request)
    {
        $data = $request->all();

        // Log the incoming payload for debugging purposes.
        Log::info('WhatsApp webhook payload:', $data);

        // Check if the expected message data exists.
        if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
            return response('No message found in payload', 200);
        }

        $messageData = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $sender      = $messageData['from']; // Sender's phone number
        $messageText = $messageData['text']['body'] ?? '';

        if (!$messageText) {
            return response('No text message found', 200);
        }

        // ==========================
        // Step 2A. Call ChatGPT API
        // ==========================
        $openaiResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model'    => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user',   'content' => $messageText],
            ],
        ]);

        if (!$openaiResponse->successful()) {
            Log::error('Error from ChatGPT API:', ['response' => $openaiResponse->body()]);
            return response('Error processing your message', 500);
        }

        $chatGptData  = $openaiResponse->json();
        $replyMessage = $chatGptData['choices'][0]['message']['content'] 
                        ?? 'Sorry, I could not generate a response.';

        // ================================
        // Step 2B. Send reply via WhatsApp
        // ================================
        $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $metaToken     = env('META_WHATSAPP_TOKEN');

        $whatsappResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $metaToken,
            'Content-Type'  => 'application/json',
        ])->post("https://graph.facebook.com/v15.0/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $sender,
            'type'              => 'text',
            'text'              => ['body' => $replyMessage],
        ]);

        if (!$whatsappResponse->successful()) {
            Log::error('Error sending message via WhatsApp API:', ['response' => $whatsappResponse->body()]);
        }

        return response('Message processed', 200);
    }




    
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