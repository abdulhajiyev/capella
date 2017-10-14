<?php

/**
 * Class UriDispatcher
 */
class UriDispatcher
{
    public $path;
    public $filterList;
    public $id;

    private $pathParts;
    private $rawFilters;

    /**
     * @param string $uri URI to dispatch
     * @param array $filterList Dictionary of supported filters
     */
    public function __construct($uri, $filterList)
    {
        $this->path = rawurldecode($uri);
        // Splits path into parts, cutting the first '/' for elements purity
        $this->pathParts = explode('/', substr($this->path, 1));
        $this->id        = $this->pathParts[0];
        // Slices filters and additional parameters list from pathParts
        $this->rawFilters = array_slice($this->pathParts, 1);
        // Dictionary of supported filters
        $this->filterList = $filterList;
    }

    /**
     * Parses all pairs param=paramContent from array and creates [$param => $paramContent]
     * @param $varBlock String block in format variable|type of parameter
     * @param $paramString Raw string of parameters
     * @param $delimiter Delimiter for current parameter
     * @return array Pair "variable" => $variable, "value" => $value
     */
    private function getParamData($varBlock, $paramString, $delimiter = false)
    {
        $variable = explode('|', $varBlock)[0];
        $type     = explode('|', $varBlock)[1];
        if ($delimiter) {
            $value = strstr($paramString, $delimiter, true);
        } else {
            $value = $paramString;
        }

        settype($value, $type);
        $param = array("variable" => $variable, "value" => $value);
        return $param;
    }

    /**
     * Parses string of parameters by pattern and returns all contained variables with values
     * @param $filterId Filter index in raw filters list
     * @return array All variables, contained in parameters, with values
     */
    private function parseParamsData($filterId)
    {
        $paramString    = $this->rawFilters[$filterId + 1];
        $pattern        = $this->filterList[$this->rawFilters[$filterId]]['pattern'];

        // Split raw paramString on alternate parts of variable blocks (variable|type) and values
        $paramsParts    = preg_split("/[{}]+/", $pattern);

        // Delete meaningless elements beyond variable blocks
        $paramsParts    = array_slice($paramsParts, 1, -1);
        $params         = array();
        $paramsPartsCnt = count($paramsParts);

        // Get all parameters data
        for ($it = 0; $it < $paramsPartsCnt - 1; $it += 2) {
            $delimiter = $paramsParts[$it + 1];

            // Structure variable block + value
            $paramData = $this->getParamData($paramsParts[$it],
                $paramString, $paramsParts[$it + 1]);

            // Cut processed part of raw parameters string
            $paramString                    = substr(strstr($paramString, $delimiter), 1);
            $params[$paramData["variable"]] = $paramData["value"];
        }

        // Process last element (with no delimiter)
        $paramData = $this->getParamData(
            $paramsParts[$paramsPartsCnt - 1], $paramString);
        $params[$paramData["variable"]] = $paramData["value"];
        return $params;
    }

    /**
     * Parses raw filters element and returns formatted filter data dictionary
     * @param $filterId Filter index in raw filters list
     */
    public function parseFilterData($filterId)
    {
        // Check if the filter exists
        if (array_key_exists($this->rawFilters[$filterId], $this->filterList)) {

            $filter = $this->filterList[$this->rawFilters[$filterId]]['title'];

            // "{id}/{filter_name}//{filter_name}/..." case check
            if (strlen($this->rawFilters[$filterId + 1]) > 0) {

                // Get structured params array
                $params = $this->parseParamsData($filterId);
                $data   = array('status' => 'Ok', 'filter' => $filter, 'params' => $params);

            } else {
                $data = array('status' => 'Not enough info to ' . $filter, 'filter' => $filter);
            }
        } else {
            $data = array('status' => 'Filter syntax error');
        }

        return $data;
    }

    /**
     * Parses raw filters list and returns formatted filters data array
     * @return array in format [['status' => $f1Status,'filter1' => $f1Title, 'params' => f1Params],
     * ['status' => $f2Status,'filter1' => $f2Title, 'params' => f2Params]]
     */
    public function parseFilters()
    {
        $rawFiltersCount = count($this->rawFilters);
        if ($rawFiltersCount == 0) {
            $filtersData = false;
        } else {
            $filtersData = array();

            // "{id}/{filter_name}" only case check
            if ($rawFiltersCount == 1) {
                $filtersData = [['status' => 'Not enough info']];
            }

            // Raw filters' data loop
            for ($filterId = 0; $filterId < $rawFiltersCount - 1; $filterId += 2) {
                $data = $this->parseFilterData($filterId);

                // Push output data for one filter
                array_push($filtersData, $data);
            }
        }

        return $filtersData;
    }
}
