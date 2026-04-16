<?php

if (!function_exists('toolshare_dispute_queue_meta')) {
    function toolshare_dispute_queue_meta(string $queue): array
    {
        if ($queue === 'renter') {
            return [
                'title' => 'Renter Disputes',
                'badge' => 'Renter Refund Review',
                'intro' => 'Review pickup and service complaints raised by renters, document evidence quality, and track the fair refund path before closing the case.',
                'focus' => 'Start with pickup failures, non-delivery claims, and tool-condition complaints. Prioritize unresolved cases where the renter may be blocked from starting the rental.',
                'pending_label' => 'Open Claims',
                'pending_note' => 'New renter-reported problems still waiting for operations triage.',
                'reviewing_label' => 'In Refund Review',
                'reviewing_note' => 'Claims being assessed for refund, replacement, or manual resolution.',
                'closed_label' => 'Closed Claims',
                'closed_note' => 'Renter disputes with a final outcome already documented.',
                'risk_label' => 'Pickup Risk Cases',
                'risk_note' => 'Claims near the pickup window that may need quick intervention.',
                'queue_title' => 'Renter Dispute Queue',
                'panel_subtitle' => 'Review the renter claim, confirm what happened at pickup or handoff, and record the recommended service outcome.',
                'support_title' => 'Decision Support',
                'support_copy' => "Full Refund: renter did not receive the tool, the owner no-showed, or the tool was unusable at handoff.\nPartial Refund: part of the service failed, but some value was still delivered.\nReplacement / Manual Resolution: the team needs an alternate arrangement, callback, or offline coordination.\nDenied Claim: the evidence does not support the renter's complaint or duplicates an already resolved case.\n\nUse resolution notes to explain what was verified and what next action should happen for the renter.",
                'decision_options' => ['pending', 'deny', 'full_refund', 'partial_refund', 'replacement_or_manual_resolution'],
                'primary_party_label' => 'Renter',
                'secondary_party_label' => 'Owner',
                'amount_label' => 'Paid Amount',
                'status_empty' => 'No renter disputes matched the current filters.',
                'select_empty' => 'Select a renter dispute from the queue to open the refund review panel.',
            ];
        }

        return [
            'title' => 'Owner Disputes',
            'badge' => 'Case Review Workspace',
            'intro' => 'Review escalated return and damage cases with a clear workflow: inspect the evidence, understand the financial exposure, document the reasoning, and close the case with a fair and traceable decision.',
            'focus' => 'Start with cases still in Pending or Reviewing, then check deposit impact and evidence quality before closing any case.',
            'pending_label' => 'Pending Cases',
            'pending_note' => 'Newly raised disputes still waiting for active operations review.',
            'reviewing_label' => 'Reviewing',
            'reviewing_note' => 'Cases that are actively being assessed or need more evidence.',
            'closed_label' => 'Closed Cases',
            'closed_note' => 'Resolved or rejected disputes with a final recorded outcome.',
            'risk_label' => 'High Deposit Cases',
            'risk_note' => 'Disputes involving higher held deposits that may need closer attention.',
            'queue_title' => 'Owner Dispute Queue',
            'panel_subtitle' => 'Review the case facts, inspect the evidence, and document a fair financial outcome before closure.',
            'support_title' => 'Decision Support',
            'support_copy' => "Full Refund: evidence is weak, damage is not proven, or the issue looks like normal wear.\nPartial Deduction: renter responsibility is supported, but the full deposit would be excessive.\nFull Forfeit: strong evidence shows severe damage, major loss, or unusable return condition.\nRejected Dispute: the claim itself is invalid, duplicated, or should close in the renter's favor.\n\nAlways explain what happened, what evidence was reviewed, and why the deduction or refund amount is fair.",
            'decision_options' => ['pending', 'full_refund', 'partial_deduction', 'full_forfeit'],
            'primary_party_label' => 'Owner',
            'secondary_party_label' => 'Renter',
            'amount_label' => 'Deposit Held',
            'status_empty' => 'No owner disputes matched the current filters.',
            'select_empty' => 'Select an owner dispute from the queue to open the case review panel.',
        ];
    }
}
