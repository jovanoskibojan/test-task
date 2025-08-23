<?php
class GoogleDocProcessor {
    public function process($url) {
        if (!$this->isValidGoogleDocUrl($url)) {
            return [
                'status' => 'error',
                'message' => 'Provided URL is not a valid Google Doc link'
            ];
        }

        if (!$this->isPublic($url)) {
            return [
                'status' => 'error',
                'message' => 'Document is not public'
            ];
        }

        $data = $this->extractInfo($url);

        return [
            'status' => 'success',
            'data' => $data
        ];
    }

    /**
     * Check if URL is a valid Google Doc link
     */
    private function isValidGoogleDocUrl($url) {
        // Basic pattern for Google Docs URLs
        $pattern = '/^https:\/\/docs\.google\.com\/document\/d\/[a-zA-Z0-9_-]+(\/.*)?$/';
        return preg_match($pattern, $url);
    }

    /**
     * Check if the Google Doc is public
     */
    private function isPublic($url) {
        $docId = $this->extractDocId($url);
        if (!$docId) return false;

        $exportUrl = "https://docs.google.com/document/d/$docId/export?format=html";

        // Use cURL to fetch content
        $ch = curl_init($exportUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirects
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If HTTP 200 and content is not empty, document is likely public
        return $httpCode === 200 && !empty($response);
    }

    /**
     * Extract Google Doc ID from URL
     */
    private function extractDocId($url) {
        preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches);
        return $matches[1] ?? null;
    }

    private function extractInfo($url) {
        $docId = $this->extractDocId($url);
        $exportUrl = "https://docs.google.com/document/d/$docId/export?format=html";

        $html = $this->fetchHtml($exportUrl);
        if (!$html) return [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // --- Extract <style> from <head> ---
        $styles = '';
        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head) {
            foreach ($head->getElementsByTagName('style') as $styleTag) {
                $styles .= $dom->saveHTML($styleTag);
            }
        }

        // --- Extract <body> content ---
        $body = $dom->getElementsByTagName('body')->item(0);

        // --- Extract plain text from body ---
        $plainText = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $plainText .= $child->textContent . "\n";
            }
            $plainText = trim($plainText);
        }

        // --- Extract Meta Title ---
        $title = '';
        if (preg_match('/Meta\s*Title\s*:\s*(.+)/i', $plainText, $matches)) {
            $title = trim($matches[1]);
        }

        // --- Extract Meta Description ---
        $description = '';
        if (preg_match('/Meta\s*Description\s*:\s*(.+)/i', $plainText, $matches)) {
            $description = trim($matches[1]);
        }

        // --- Remove Meta Title & Description from plain text ---
        $plainText = preg_replace([
            '/Meta\s*Title\s*:\s*.+(\r?\n|\r)?/i',
            '/Meta\s*Description\s*:\s*.+(\r?\n|\r)?/i'
        ], '', $plainText);
        $plainText = trim($plainText);

        // --- Remove paragraphs containing Meta Title / Description from HTML ---
        if ($body) {
            $ps = $body->getElementsByTagName('p');
            $psArray = [];
            foreach ($ps as $p) $psArray[] = $p; // clone into array to safely remove nodes

            foreach ($ps as $p) {
                $text = $p->textContent;
                if (stripos($text, 'Meta Title:') !== false || stripos($text, 'Meta Description:') !== false) {
                    $p->parentNode->removeChild($p);
                }
            }
        }

        // Count all links in the body
        $linkCount = 0;
        if ($body) {
            $links = $body->getElementsByTagName('a');
            $linkCount = $links->length;
        }

        $imageStats = $this->replaceImagesWithAlt($dom);

        // --- Combine styles + body content ---
        $cleanHtml = $styles;
        if ($body) {
            foreach ($body->childNodes as $child) {
                $cleanHtml .= $dom->saveHTML($child);
            }
        }
        $cleanHtml = trim($cleanHtml);

        return [
            'title' => $title,
            'description' => $description,
            'html' => $cleanHtml,
            'plain' => $plainText,
            'imageStats' => $imageStats,
            'links' => $linkCount,
        ];
    }

    private function replaceImagesWithAlt(DOMDocument $dom) {
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a');

        foreach ($links as $link) {
            $text = trim($link->textContent);

            if (preg_match('/^IMAGE \d+$/i', $text)) {
                // Decode href to remove escaped quotes
                $href = html_entity_decode($link->getAttribute('href'));

                // Extract Google Drive file ID from q= parameter
                if (preg_match('/q=(https:\/\/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)\/view)/', $href, $matches)) {
                    $fileId = $matches[2];

                    // Proper Google Drive image URL for <img>
                    $imgSrc = "https://drive.google.com/uc?export=view&id=$fileId";

                    $altText = '';
                    $p = $link->parentNode->parentNode; // <p> node
                    foreach ($p->getElementsByTagName('span') as $span) {
                        if (preg_match('/Alt\s*tag\s*[:：]\s*(.+)/i', $span->textContent, $altMatches)) {
                            $altText = $altMatches[1];

                            // Clean alt text: remove quotes, smart quotes, trim whitespace
                            $altText = trim($altText);
                            $altText = str_replace(['“','”','"','�'], '', $altText);

                            break;
                        }
                    }

                    // Create <img>
                    $img = $dom->createElement('img');
                    $img->setAttribute('src', $imgSrc);
                    $img->setAttribute('alt', $altText);

                    // Replace the entire <p> with <img>
                    $p->parentNode->replaceChild($img, $p);

                    $images = $dom->getElementsByTagName('img');
                    $stats['total'] = 0;
                    $stats['drive'] = 0;
                    $stats['public'] = 0;

                    foreach ($images as $img) {
                        $stats['total']++;
                        $src = $img->getAttribute('src');

                        // Check if it is a Google Drive link
                        if (preg_match('/drive\.google\.com\/uc\?export=view&id=([a-zA-Z0-9_-]+)/', $src, $matches)) {
                            $stats['drive']++;
                            $fileId = $matches[1];

                            // Check if the file is public
                            $headers = @get_headers("https://drive.google.com/uc?export=view&id=$fileId");
                            if ($headers && strpos($headers[0], '200') === false) {
                                $stats['public']++;
                            }
                        }
                    }
                }
            }
        }
        $data['total'] = $stats['total'];
        $data['private'] = $stats['total'] - $stats['public'];
        $data['not_drive'] = $stats['total'] - $stats['drive'];

        return $data;
    }

    /**
     * Remove class and id attributes from node and its children
     */
    private function removeClassesAndIds(DOMNode $node) {
        if ($node->hasAttributes()) {
            if ($node->attributes->getNamedItem('class')) {
                $node->removeAttribute('class');
            }
            if ($node->attributes->getNamedItem('id')) {
                $node->removeAttribute('id');
            }
        }

        foreach ($node->childNodes as $child) {
            $this->removeClassesAndIds($child);
        }
    }

    private function fetchHtml($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200 ? $response : null;
    }
}