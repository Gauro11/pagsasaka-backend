<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question;

class QuestionController extends Controller
{
    public function createQuestion(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'question' => 'required|string|max:255',
        ]);

        try {
            // Create the question
            $question = Question::create($validated);

            // Return a success response
            return response()->json([
                'isSuccess' => true,
                'message' => 'Question created successfully.',
                'question' => $question,
            ], 201);
        } catch (\Exception $e) {
            // Return an error response
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to create the question.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllQuestions()
    {
        $questions = Question::all();

        return response()->json([
            'isSuccess' => true,
            'questions' => $questions,
        ], 200);
    }
}

