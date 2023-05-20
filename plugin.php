<?php
/**
 * Plugin name: AI-Shred
 * Version: 1.0.0
 */

define( "QUESTIONNAIRE_PAGE_ID", 274 );

require_once( __DIR__ . '/vendor/autoload.php');

/**
 * Create PDF from HTML
 * 
 * @return string Filepath of PDF
 */
function create_pdf( string $html, string $title = "Plan" ): string {
    // Create a new TCPDF instance
    $pdf = new TCPDF();

    // Set document information
    $pdf->SetCreator('AI-Shred');
    $pdf->SetAuthor('AI-Shred');
    $pdf->SetTitle( $title );

    // Add a page
    $pdf->AddPage();

    // Set font settings
    $pdf->SetFont('helvetica', '', 12);

    // Write some content
    $pdf->writeHTML($html);

    // Save PDF to file
    $filename = __DIR__ . "/pdfs/" . uniqid( true ) . ".pdf";
    $pdf->Output($filename, 'F');

    return $filename;
}

function get_meal_plan_questions() {
    return json_decode( '[
        {
          "question": "What is your gender?",
          "answer": ["Male", "Female"]
        },
        {
          "question": "What is your age?",
          "answer": "freetext"
        },
        {
          "question": "What is your height (in centimeters)?",
          "answer": "freetext"
        },
        {
          "question": "What is your current weight (in kilograms)?",
          "answer": "freetext"
        },
        {
          "question": "What is your goal?",
          "answer": ["Weight loss", "Muscle gain", "General fitness"]
        },
        {
          "question": "How active are you?",
          "answer": ["Sedentary (little to no exercise)", "Lightly active (light exercise/sports 1-3 days a week)", "Moderately active (moderate exercise/sports 3-5 days a week)", "Very active (hard exercise/sports 6-7 days a week)", "Extra active (very hard exercise/sports and a physical job)"]
        },
        {
          "question": "Do you have any food allergies or dietary restrictions?",
          "answer": "freetext"
        },
        {
          "question": "How many meals per day do you prefer?",
          "answer": ["3 meals", "4 meals", "5 meals"]
        },
        {
          "question": "Do you have any specific dietary preferences?",
          "answer": "freetext"
        },
        {
          "question": "How many days per week can you dedicate to meal preparation?",
          "answer": "freetext"
        }
    ]' );
}

function meal_plan_questionnaire( int $order_id, int $product_id ) {
    $questions = get_meal_plan_questions();

    $questionnaire = '<form action="/index.php?answer_meal_plan_questionnaire=true" method="POST"><input type="hidden" name="ai_order" value="'.$order_id.'" /><input type="hidden" name="ai_product" value="'.$product_id.'" />';

    foreach( $questions as $qid => $question ) {
        $questionnaire .= '<b>' . esc_html( $question->question ) . '</b><br />';

        if( is_array( $question->answer ) ) {
            foreach( $question->answer as $aid => $answer ) {
                $questionnaire .= '<label><input type="radio" name="answer['.$qid.']" value="'.$aid.'"> ' . esc_html( $answer ) . '</label><br />';
            }
        } else {
            $questionnaire .= '<textarea name="answer['.$qid.']"></textarea><br />';
        }

        $questionnaire .= '<br />';
    }

    $questionnaire .= '<input type="submit" value="Send questionnaire" /><br /><br /></form>';

    return $questionnaire;
};

add_action( "woocommerce_after_register_post_type", function() {
    if( isset( $_GET['answer_meal_plan_questionnaire'] ) ) {
        $order_id = absint( $_POST['ai_order'] );
        $order = wc_get_order( $order_id );
    
        if( ! $order ) {
            die( "Order not found: " . $order_id );
        }

        $questions = get_meal_plan_questions();
        
        $prompt = "You are a personal trainer who is creating a meal plan for a customer. Write the meal plan in the first person and address the recipient as \"you\". Create a meal plan accoring to the below questionnaire. Create the meal plan in HTML format, using h1, h2, h3 and p tags. Create a very detailed meal plan for a whole week with all meals included. Also add choices to the meals so that meals can be swapped with other meals or some ingredients can be substituted.\n\n";

        foreach( $questions as $qid => $question ) {
            $prompt .= $question->question . "\n";

            if( is_array( $question->answer ) ) {
                $prompt .= $question->answer[$_POST['answer'][$qid]]."\n\n";
            } else {
                $prompt .= $_POST['answer'][$qid]."\n\n";
            }
        }

        $settings = include( __DIR__ . "/settings.php" );
        include( __DIR__ . "/chatgpt.php" );

        $response = send_chatgpt_message( $prompt, $settings['api_key'] );

        $pdf_file = create_pdf( $response );

        $product_id = absint( $_POST['ai_product'] );
        $order->add_meta_data( "_aishred_plan_" . $product_id, $pdf_file, true );
        $order->add_meta_data( "_aishred_plan_generated_" . $product_id, "yes", true );
        $order->save();

        header( "Location: /index.php?page_id=".QUESTIONNAIRE_PAGE_ID."&download_meal_plan=true&ai_order=" . $order_id . "&ai_product=" . $product_id );
        exit;
    }

    if( isset( $_GET['download_meal_plan'] ) ) {
        $order_id = absint( $_GET['ai_order'] );
        $order = wc_get_order( $order_id );
    
        if( ! $order ) {
            die( "Order not found: " . $order_id );
        }
    
        $product_id = absint( $_GET['ai_product'] );
        $plan_generated = $order->get_meta( "_aishred_plan_generated_" . $product_id ) === "yes";
    
        if( $plan_generated ) {
            header( "Content-type: application/pdf" );
            readfile( $order->get_meta( "_aishred_plan_" . $product_id ) );
            die();
        }

        add_shortcode( "questionnaire", function() use( $order_id, $product_id ) {
            return meal_plan_questionnaire( $order_id, $product_id );
        } );
    }
} );

add_filter( "woocommerce_order_get_downloadable_items", function(
    array $downloads,
    WC_Order $order,
) {
    foreach( $downloads as $i => $download ) {
        $downloads[$i]['download_url'] = "/index.php?page_id=".QUESTIONNAIRE_PAGE_ID."&download_meal_plan=true&ai_order=" . $order->get_id() . "&ai_product=" . $download['product_id'];
    }
    
    return $downloads;
}, 10, 2 );
