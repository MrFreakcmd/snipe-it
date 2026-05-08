<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('onboard_employee')]
#[Title('Onboard Employee')]
#[Description('Guide through creating a new employee account and assigning appropriate equipment and licenses')]
class OnboardEmployeePrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $name = $request->get('name');
        $department = $request->get('department');
        $location = $request->get('location');
        $title = $request->get('title');

        $context = collect([
            $department ? "Department: {$department}" : null,
            $location   ? "Location: {$location}"     : null,
            $title      ? "Job title: {$title}"       : null,
        ])->filter()->implode("\n");

        $prompt = <<<TEXT
        You are helping onboard a new employee: {$name}.
        {$context}

        Please complete the following onboarding steps using the available tools:

        1. Create a new user account for {$name} with the details provided above.
        2. Search for available (undeployed) assets suitable for their role — typically a laptop and any other standard equipment for their department or location.
        3. Check out the selected assets to the new user.
        4. Check whether any software license seats are available that should be assigned (e.g. productivity suites, VPN, etc.) and assign them.
        5. Summarise what was set up: the user account created, assets checked out, and licenses assigned.

        Ask for any missing details (such as email address, username, or specific equipment needs) before proceeding if necessary.
        TEXT;

        return Response::text(trim($prompt));
    }

    public function arguments(): array
    {
        return [
            new Argument('name', 'Full name of the new employee', required: true),
            new Argument('department', 'Department the employee will join', required: false),
            new Argument('location', 'Primary office location', required: false),
            new Argument('title', 'Job title', required: false),
        ];
    }
}
