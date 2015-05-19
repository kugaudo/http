<?php

namespace Sabre\HTTP;

/**
 * HTTP utility methods
 *
 * @copyright Copyright (C) 2009-2015 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @author Paul Voegler
 * @license http://sabre.io/license/ Modified BSD License
 */
class Util {

    /**
     * This method can be used to aid with content negotiation.
     *
     * It takes 2 arguments, the $acceptHeaderValue, which usually comes from
     * an Accept header, and $availableOptions, which contains an array of
     * items that the server can support.
     *
     * The result of this function will be the 'best possible option'. If no
     * best possible option could be found, null is returned.
     *
     * When it's null you can according to the spec either return a default, or
     * you can choose to emit 406 Not Acceptable.
     *
     * The method also accepts sending 'null' for the $acceptHeaderValue,
     * implying that no accept header was sent.
     *
     * @param string|null $acceptHeaderValue
     * @param array $availableOptions
     * @return string|null
     */
    static function negotiateContentType($acceptHeaderValue, array $availableOptions) {

        if (!$acceptHeaderValue) {
            // Grabbing the first in the list.
            return reset($availableOptions);
        }

        $proposals = array_map(
            ['self', 'parseMimeType'],
            explode(',', $acceptHeaderValue)
        );

        // Ensuring array keys are reset.
        $availableOptions = array_values($availableOptions);

        $options = array_map(
            ['self', 'parseMimeType'],
            $availableOptions
        );

        $lastQuality = 0;
        $lastSpecificity = 0;
        $lastOptionIndex = 0;
        $lastChoice = null;

        foreach($proposals as $proposal) {

            // Ignoring broken values.
            if (is_null($proposal)) continue;

            // If the quality is lower we don't have to bother comparing.
            if ($proposal['quality'] < $lastQuality) {
                continue;
            }

            foreach($options as $optionIndex => $option) {

                if ($proposal['type'] !== '*' && $proposal['type'] !== $option['type']) {
                    // no match on type.
                    continue;
                }
                if ($proposal['subType'] !== '*' && $proposal['subType'] !== $option['subType']) {
                    // no match on subtype.
                    continue;
                }

                // Any parameters appearing on the options must appear on
                // proposals.
                foreach($option['parameters'] as $paramName => $paramValue) {
                    if (!array_key_exists($paramName, $proposal['parameters'])) {
                        continue 2;
                    }
                    if ($paramValue !== $proposal['parameters'][$paramName]) {
                        continue 2;
                    }
                }

                // If we got here, we have a match on parameters, type and
                // subtype. We need to calculate a score for how specific the
                // match was.
                $specificity =
                    ($proposal['type'] !== '*' ? 20 : 0) +
                    ($proposal['subType'] !== '*' ? 10 : 0) +
                    count($option['parameters']);


                // Does this entry win?
                if (
                    ($proposal['quality'] > $lastQuality) ||
                    ($proposal['quality'] === $lastQuality && $specificity > $lastSpecificity) ||
                    ($proposal['quality'] === $lastQuality && $specificity === $lastSpecificity && $optionIndex < $lastOptionIndex)
                ) {

                    $lastQuality = $proposal['quality'];
                    $lastSpecificity = $specificity;
                    $lastOptionIndex = $optionIndex;
                    $lastChoice = $availableOptions[$optionIndex];

                }

            }

        }

        return $lastChoice;

    }

    /**
     * Parses a mime-type and splits it into:
     *
     * 1. type
     * 2. subtype
     * 3. quality
     * 4. parameters
     *
     * @param string $str
     * @return array
     */
    private static function parseMimeType($str) {

        $parameters = [];
        // If no q= parameter appears, then quality = 1.
        $quality = 1;

        $parts = explode(';', $str);

        // The first part is the mime-type.
        $mimeType = array_shift($parts);

        $mimeType = explode('/', trim($mimeType));
        if (count($mimeType) !== 2) {
            // Illegal value
            return null;
        }
        list($type, $subType) = $mimeType;

        foreach($parts as $part) {

            $part = trim($part);
            if (strpos($part, '=')) {
                list($partName, $partValue) =
                    explode('=', $part, 2);
            } else {
                $partName = $part;
                $partValue = null;
            }

            // The quality parameter, if it appears, also marks the end of
            // the parameter list. Anything after the q= counts as an
            // 'accept extension' and could introduce new semantics in
            // content-negotation.
            if ($partName !== 'q') {
                $parameters[$partName] = $part;
            } else {
                $quality = (float)$partValue;
                break; // Stop parsing parts
            }

        }

        return [
            'type'       => $type,
            'subType'    => $subType,
            'quality'    => $quality,
            'parameters' => $parameters,
        ];

    }

    /**
     * Deprecated! Use negotiateContentType.
     *
     * @deprecated
     * @param string|null $acceptHeader
     * @param array $availableOptions
     * @return string|null
     */
    static function negotiate($acceptHeaderValue, array $availableOptions) {

        return self::negotiateContentType($acceptHeaderValue, $availableOptions);

    }

    /**
     * Parses a RFC2616-compatible date string
     *
     * This method returns false if the date is invalid
     *
     * @deprecated Use HeaderHelper::parseHttpDate
     * @param string $dateHeader
     * @return bool|DateTime
     */
    static function parseHTTPDate($dateHeader) {

        return HeaderHelper::parseDate($dateHeader);

    }

    /**
     * Transforms a DateTime object to HTTP's most common date format.
     *
     * We're serializing it as the RFC 1123 date, which, for HTTP must be
     * specified as GMT.
     *
     * @deprecated Use HeaderHelper::toToHttpDate
     * @param \DateTime $dateTime
     * @return string
     */
    static function toHTTPDate(\DateTime $dateTime) {

        return HeaderHelper::toDate($dateTime);

    }
}
