<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Revise your syllabus quickly with PDF Q&A extraction. Get focused answers from your syllabus PDFs for better study efficiency.">
    <meta name="keywords" content="student, revise syllabus, PDF Q&A extraction, study aid, exam preparation, efficient studying">
    <meta name="author" content="Your Name or Company">
    <title>Revise Syllabus Quickly with PDF Q&A Extraction</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }

        .container {
            max-width: 800px;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            transition: background-color 0.3s;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .form-label {
            font-weight: bold;
        }

        .alert {
            border-radius: 10px;
            font-size: 16px;
        }

        .chat-box {
            max-height: 400px;
            overflow-y: auto;
            background-color: #f1f1f1;
            padding: 20px;
            border-radius: 10px;
        }

        .chat-message {
            background-color: #007bff;
            color: white;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .response-message {
            background-color: #e1e1e1;
            color: #333;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
            white-space: pre-wrap;
        }

        .text-center {
            font-size: 2rem;
            color: #333;
            margin-bottom: 20px;
        }

        .file-name {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>

<body>

    <div class="container mt-5">
        <h1 class="text-center">Revise Your Syllabus Quickly</h1>
        <p class="text-center text-muted">Upload your syllabus PDF and extract relevant Q&A to enhance your study efficiency.</p>

        <!-- Upload Form -->
        <div class="card mt-4">
            <div class="card-header">
                Upload Your Syllabus PDF
            </div>
            <div class="card-body">
                <form id="pdfForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label for="file" class="form-label">Choose PDF File</label>
                        <input type="file" class="form-control" id="file" name="file" accept=".pdf" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Upload and Extract Q&A</button>
                </form>
            </div>
        </div>

        <div class="card mt-4" id="resultCard" style="display: none;">
            <div class="card-header">
                Processed Result
            </div>
            <div class="card-body">
                <h5>Uploaded File: <span id="fileName" class="file-name"></span></h5>

                <div class="chat-box" id="chatBox">
                    <div class="chat-message">Processing your PDF...</div>
                </div>

                <h5>QA Response:</h5>
                <div id="qaResponse" class="response-message"></div>
            </div>
        </div>

        <!-- Error Message -->
        <div class="alert alert-danger mt-4" id="errorMessage" style="display: none;"></div>
    </div>

    <script>
        document.getElementById('pdfForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            // Clear previous messages
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('resultCard').style.display = 'none';

            const formData = new FormData();
            formData.append('file', document.getElementById('file').files[0]);

            try {
                const response = await axios.post('{{ route("handle.pdf") }}', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    }
                });

                const data = response.data;

                if (data.success) {
                    // Populate the result section
                    document.getElementById('fileName').textContent = data.fileName;

                    // Show the QA response with formatted questions and answers
                    const chatBox = document.getElementById('chatBox');
                    chatBox.innerHTML = ''; // Clear existing content

                    // Assuming the extracted text has a format like "Q: Question text. A: Answer text."
                    const qaPairs = data.extractedText.split('\n'); // Split by newlines

                    qaPairs.forEach(pair => {
                        const questionAnswer = pair.split('A:');
                        if (questionAnswer.length === 2) {
                            const question = questionAnswer[0].replace('Q:', '').trim();
                            const answer = questionAnswer[1].trim();

                            // Create the question element with <strong> to make it bold
                            const questionDiv = document.createElement('div');
                            questionDiv.classList.add('chat-message');
                            questionDiv.innerHTML = `<strong>Q: ${question}</strong>`;

                            // Add the Copy button next to the question
                            const copyButton = document.createElement('button');
                            copyButton.textContent = 'Copy';
                            copyButton.classList.add('btn', 'btn-sm', 'btn-secondary', 'ml-2');
                            copyButton.addEventListener('click', function () {
                                copyToClipboard(question);
                            });

                            // Append the copy button to the question
                            questionDiv.appendChild(copyButton);

                            // Add the question to the chat box
                            chatBox.appendChild(questionDiv);

                            // Create the answer element
                            const answerDiv = document.createElement('div');
                            answerDiv.classList.add('response-message');
                            answerDiv.textContent = answer;
                            chatBox.appendChild(answerDiv);
                        }
                    });

                    // Display QA response (optional for extra text formatting)
                    document.getElementById('qaResponse').textContent = data.qaResponse;

                    document.getElementById('resultCard').style.display = 'block';
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                document.getElementById('errorMessage').textContent = error.response?.data?.message || error.message;
                document.getElementById('errorMessage').style.display = 'block';
            }
        });


    </script>

</body>

</html>
    