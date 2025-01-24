<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

class PdfController extends Controller
{
    /**
     * Handle the uploaded PDF, extract text, and send it to the Llama API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handlePdf(Request $request)
    {
        // Validate the uploaded PDF
        $request->validate([
            'file' => 'required|mimes:pdf|max:2048', // Max 2MB
        ]);

        try {
            // Upload the PDF
            $filePath = $this->uploadPdf($request->file('file'));

            // Extract text from the PDF
            $extractedText = $this->extractTextFromPdf($filePath);

            // Send text to Llama API
            $qaResponse = $this->getLlamaApiResponse($extractedText);

            return response()->json([
                'success' => true,
                'fileName' => basename($filePath),
                'extractedText' => $extractedText,
                'qaResponse' => $qaResponse,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload the PDF file and return the stored path.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return string
     */
    private function uploadPdf($file)
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
        return $file->storeAs('pdfs', $fileName, 'public');
    }

    /**
     * Extract text from the uploaded PDF file.
     *
     * @param  string  $filePath
     * @return string
     */
    private function extractTextFromPdf($filePath)
    {
        $parser = new Parser();
        $pdfPath = storage_path('app/public/' . $filePath);
        $document = $parser->parseFile($pdfPath);
        return $document->getText();
    }

    /**
     * Send extracted text to the Llama API and get the response.
     *
     * @param  string  $text
     * @return string|null
     */
    private function getLlamaApiResponse($text)
    {
        $apiKey = env('GROQ_API_KEY');
        $llamaApiUrl = env('LLAMA_API_URL');
    
        $enhancedText = "Cover all the important points and provide a complete, detailed explanation, including both questions and answers with example, based on the following content:\n\n" . $text;
    
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($llamaApiUrl, [
            'messages' => [
                ['role' => 'user', 'content' => $enhancedText],
            ],
            'model' => 'llama-3.3-70b-versatile',
        ]);
    
        if ($response->successful()) {
            return $response->json()['choices'][0]['message']['content'] ?? 'No response from Llama.';
        }
    
        return 'Error: Unable to get a response from Llama API.';
    }
    
}

