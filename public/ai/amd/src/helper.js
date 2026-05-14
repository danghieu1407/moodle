// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The helper module or AI Subsystem.
 *
 * @module     core_ai/helper
 * @copyright  2024 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class AIHelper {
    /**
     * Escape text to prevent rendering user-provided HTML.
     *
     * @param {String} text The text to escape.
     * @returns {String}
     */
    static escapeHtml(text) {
        const replacements = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            '\'': '&#039;',
        };

        return text.replace(/[&<>"']/g, character => replacements[character]);
    }

    /**
     * Replace markdown bold and inline code formatting.
     *
     * @param {String} text The text to replace.
     * @returns {String}
     */
    static replaceInlineMarkdown(text) {
        let formattedText = text;
        formattedText = formattedText.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        formattedText = formattedText.replace(/`(.+?)`/g, '<code>$1</code>');

        return formattedText;
    }

    /**
     * Format markdown text into semantic HTML.
     *
     * @param {String} text The text to format.
     * @returns {String}
     */
    static markdownToHtml(text) {
        const normalisedText = text.replace(/\r\n/g, '\n').trim();
        if (!normalisedText) {
            return '';
        }

        const lines = normalisedText.split('\n');
        const html = [];
        let paragraphLines = [];
        let listType = null;
        let orderedItemOpen = false;
        let nestedUnorderedOpen = false;
        let pendingListBreak = false;

        const flushParagraph = () => {
            if (!paragraphLines.length) {
                return;
            }

            const paragraphText = this.replaceInlineMarkdown(paragraphLines.join(' ').trim());
            html.push(`<p>${paragraphText}</p>`);
            paragraphLines = [];
        };

        const closeList = () => {
            if (!listType) {
                return;
            }

            if (listType === 'ol') {
                if (nestedUnorderedOpen) {
                    html.push('</ul>');
                    nestedUnorderedOpen = false;
                }
                if (orderedItemOpen) {
                    html.push('</li>');
                    orderedItemOpen = false;
                }
                html.push('</ol>');
            } else {
                html.push('</ul>');
            }
            listType = null;
        };

        lines.forEach((line) => {
            const trimmedLine = line.trim();
            if (!trimmedLine) {
                flushParagraph();
                // A blank line may separate list items, or end a list entirely.
                // Defer the decision until we see the next non-empty line.
                pendingListBreak = Boolean(listType);
                return;
            }

            const headingMatch = trimmedLine.match(/^(#{1,6})\s+(.+)$/);
            if (headingMatch) {
                flushParagraph();
                closeList();
                pendingListBreak = false;
                const headingLevel = headingMatch[1].length;
                const headingText = this.replaceInlineMarkdown(headingMatch[2].trim());
                html.push(`<h${headingLevel}>${headingText}</h${headingLevel}>`);
                return;
            }

            const unorderedListMatch = trimmedLine.match(/^[-*]\s+(.+)$/);
            if (unorderedListMatch) {
                flushParagraph();
                const itemText = this.replaceInlineMarkdown(unorderedListMatch[1].trim());

                // Treat unordered lines following ordered lines as level-2 bullets.
                if (listType === 'ol' && orderedItemOpen) {
                    if (!nestedUnorderedOpen) {
                        html.push('<ul>');
                        nestedUnorderedOpen = true;
                    }
                    html.push(`<li>${itemText}</li>`);
                    pendingListBreak = false;
                    return;
                }

                if (pendingListBreak && listType && listType !== 'ul') {
                    closeList();
                }
                if (listType !== 'ul') {
                    closeList();
                    listType = 'ul';
                    html.push('<ul>');
                }
                html.push(`<li>${itemText}</li>`);
                pendingListBreak = false;
                return;
            }

            const orderedListMatch = trimmedLine.match(/^\d+[.)]\s+(.+)$/);
            if (orderedListMatch) {
                flushParagraph();
                if (listType === 'ul') {
                    closeList();
                }
                if (listType !== 'ol') {
                    closeList();
                    listType = 'ol';
                    html.push('<ol>');
                }
                const itemText = this.replaceInlineMarkdown(orderedListMatch[1].trim());
                if (nestedUnorderedOpen) {
                    html.push('</ul>');
                    nestedUnorderedOpen = false;
                }
                if (orderedItemOpen) {
                    html.push('</li>');
                }
                html.push(`<li>${itemText}`);
                orderedItemOpen = true;
                pendingListBreak = false;
                return;
            }

            if (pendingListBreak) {
                closeList();
                pendingListBreak = false;
            }
            closeList();
            paragraphLines.push(trimmedLine);
        });

        flushParagraph();
        closeList();

        return html.join('');
    }

    /**
     * Format the response provided by the AI model.
     *
     * @param {String} text The text to format.
     * @returns {String}
     */
    static formatResponse(text) {
        const escapedText = this.escapeHtml(text);
        return this.markdownToHtml(escapedText);
    }

    /**
     * Populate fields using settings that match key to name of input.
     *
     * @param {Object} settings The settings to populate with.
     * @param {String} containerId The target container.
     */
    static populateFields = (settings, containerId) => {
        const container = document.getElementById(containerId);

        if (container) {
            for (const [key, value] of Object.entries(settings)) {
                const field = container.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = value;
                }
            }
        }
    };

    /**
     * Reset all fields in a container.
     *
     * @param {String} containerId The target container.
     */
    static clearFields = (containerId) => {
        const container = document.getElementById(containerId);

        if (container) {
            const allFormElements = container.querySelectorAll('input, select, textarea');
            allFormElements.forEach(element => {
                if (element.type === 'checkbox' || element.type === 'radio') {
                    element.checked = false;
                } else if (element.tagName === 'SELECT') {
                    element.selectedIndex = 0;
                } else {
                    element.value = '';
                }
            });
        }
    };
}
