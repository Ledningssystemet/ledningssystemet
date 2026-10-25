<?php

namespace Ledningssystemet\Ledningssystemet\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MeetingDocument
{
    public static function mentions(array $agenda): array
    {
        Validator::make(['agenda' => $agenda], [
            'agenda' => 'array|list|max:100',
            'agenda.*' => 'array:id,title,document,activity_ids,source,description,previous_meeting_id',
            'agenda.*.id' => 'required|string|max:64|distinct|regex:/^[a-zA-Z0-9_-]+$/',
            'agenda.*.title' => 'required|string|max:255',
            'agenda.*.document' => 'required|array',
            'agenda.*.activity_ids' => 'present|array|list|max:100',
            'agenda.*.activity_ids.*' => 'integer|distinct',
            'agenda.*.source' => 'sometimes|in:custom,template,followup',
            'agenda.*.description' => 'sometimes|nullable|string|max:10000',
            'agenda.*.previous_meeting_id' => 'sometimes|integer|min:1',
        ])->validate();

        if (strlen(json_encode($agenda, JSON_THROW_ON_ERROR)) > 500000) {
            throw ValidationException::withMessages(['agenda' => __('Meeting document is too large')]);
        }

        $mentions = [];
        $count = 0;
        foreach ($agenda as $section) {
            $users = [];
            if (($section['document']['type'] ?? null) !== 'doc') {
                self::invalid();
            }
            $text = self::text($section['document'], $users, $count);
            foreach (array_unique($users) as $userId) {
                $mentions[] = ['agenda_item_id' => $section['id'], 'user_id' => $userId, 'excerpt' => mb_substr(trim($text), 0, 1000)];
            }
        }

        return $mentions;
    }

    private static function text(array $node, array &$users, int &$count, int $depth = 0): string
    {
        if (++$count > 10000 || $depth > 20 || array_diff(array_keys($node), ['type', 'content', 'attrs', 'text', 'marks'])) {
            self::invalid();
        }
        $type = $node['type'] ?? null;
        $blocks = ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'horizontalRule'];
        $children = match ($type) {
            'doc', 'blockquote' => $blocks,
            'paragraph', 'heading' => ['text', 'mention', 'hardBreak'],
            'bulletList', 'orderedList' => ['listItem'],
            'listItem' => $blocks,
            'codeBlock' => ['text'],
            'text', 'mention', 'horizontalRule', 'hardBreak' => [],
            default => null,
        };
        if ($children === null || ($depth > 0 && $type === 'doc')) {
            self::invalid();
        }
        $attrs = $node['attrs'] ?? [];
        if (! is_array($attrs)) {
            self::invalid();
        }
        $allowedAttrs = match ($type) {
            'mention' => ['id', 'label', 'mentionSuggestionChar'],
            'heading' => ['level'],
            'orderedList' => ['start', 'type'],
            'codeBlock' => ['language'],
            default => [],
        };
        if (array_diff(array_keys($attrs), $allowedAttrs)) {
            self::invalid();
        }
        if ($type === 'heading' && ! in_array($attrs['level'] ?? null, [2, 3], true)) {
            self::invalid();
        }
        if ($type === 'orderedList' && (! is_int($attrs['start'] ?? 1) || ($attrs['start'] ?? 1) < 1 || (isset($attrs['type']) && ! in_array($attrs['type'], ['1', 'a', 'A', 'i', 'I'], true)))) {
            self::invalid();
        }
        if ($type === 'codeBlock' && isset($attrs['language']) && (! is_string($attrs['language']) || strlen($attrs['language']) > 64)) {
            self::invalid();
        }
        $content = $node['content'] ?? [];
        if (! is_array($content) || ! array_is_list($content) || ($children === [] && $content !== [])) {
            self::invalid();
        }
        if (in_array($type, ['doc', 'blockquote', 'bulletList', 'orderedList', 'listItem'], true) && $content === []) {
            self::invalid();
        }
        if (isset($node['marks'])) {
            if (! is_array($node['marks']) || ! array_is_list($node['marks'])) {
                self::invalid();
            }
            foreach ($node['marks'] as $mark) {
                if (! is_array($mark) || array_keys($mark) !== ['type'] || ! in_array($mark['type'], ['bold', 'italic', 'strike', 'code'], true)) {
                    self::invalid();
                }
            }
        }
        if ($type === 'text') {
            if (! isset($node['text']) || ! is_string($node['text']) || $node['text'] === '') {
                self::invalid();
            }

            return $node['text'];
        }
        if ($type === 'mention') {
            $id = $node['attrs']['id'] ?? null;
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                self::invalid();
            }
            $label = $node['attrs']['label'] ?? '';
            if (! is_string($label) || mb_strlen($label) > 255) {
                self::invalid();
            }
            if (isset($attrs['mentionSuggestionChar']) && $attrs['mentionSuggestionChar'] !== '@') {
                self::invalid();
            }
            $users[] = (int) $id;

            return '@'.$label;
        }
        if ($type === 'listItem' && ($content[0]['type'] ?? null) !== 'paragraph') {
            self::invalid();
        }
        $text = '';
        foreach ($content as $child) {
            if (! is_array($child) || ! in_array($child['type'] ?? null, $children, true)) {
                self::invalid();
            }
            $text .= self::text($child, $users, $count, $depth + 1);
        }

        return $text.(in_array($type, ['paragraph', 'heading', 'listItem', 'hardBreak'], true) ? "\n" : '');
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['agenda' => __('Invalid meeting document')]);
    }
}
