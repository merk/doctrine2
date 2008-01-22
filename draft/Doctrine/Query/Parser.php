<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.org>.
 */

/**
 * An LL(k) parser for the context-free grammar of Doctrine Query Language.
 * Parses a DQL query, reports any errors in it, and generates the corresponding
 * SQL.
 *
 * @package     Doctrine
 * @subpackage  Query
 * @author      Janne Vanhala <jpvanhal@cc.hut.fi>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        http://www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision$
 */
class Doctrine_Query_Parser
{
    /**
     * The minimum number of tokens read after last detected error before
     * another error can be reported.
     *
     * @var int
     */
    const MIN_ERROR_DISTANCE = 2;

    /**
     * A scanner object.
     *
     * @var Doctrine_Query_Scanner
     */
    protected $_scanner;

    /**
     * An array of production objects with their names as keys.
     *
     * @var array
     */
    protected $_productions = array();

    /**
     * The next token in the query string.
     *
     * @var Doctrine_Query_Token
     */
    public $lookahead;

    /**
     * Array containing syntax and semantical errors detected in the query
     * string during parsing process.
     *
     * @var array
     */
    protected $_errors = array();

    /**
     * The number of tokens read since last error in the input string
     *
     * @var int
     */
    protected $_errorDistance = self::MIN_ERROR_DISTANCE;

    /**
     * A query printer object used to print a parse tree from the input string
     * for debugging purposes.
     *
     * @var Doctrine_Query_Printer
     */
    protected $_printer;

    /**
     * The connection object used by this query.
     *
     * @var Doctrine_Connection
     */
    protected $_conn;

    /**
     * Creates a new query parser object.
     *
     * @param string $input query string to be parsed
     * @param Doctrine_Connection The connection object the query will use.
     */
    public function __construct($input, Doctrine_Connection $conn = null)
    {
        $this->_scanner = new Doctrine_Query_Scanner($input);
        $this->_printer = new Doctrine_Query_Printer(true);

        if ($conn === null) {
            $conn = Doctrine_Manager::getInstance()->getCurrentConnection();
        }

        $this->_conn = $conn;
    }

    /**
     * Returns the connection object used by the query.
     *
     * @return Doctrine_Connection
     */
    public function getConnection()
    {
        return $this->_conn;
    }

    /**
     * Returns a production object with the given name.
     *
     * @param string $name production name
     * @return Doctrine_Query_Production
     */
    public function getProduction($name)
    {
        if ( ! isset($this->_productions[$name])) {
            $class = 'Doctrine_Query_Production_' . $name;
            $this->_productions[$name] = new $class($this);
        }

        return $this->_productions[$name];
    }

    /**
     * Attempts to match the given token with the current lookahead token.
     *
     * If they match, updates the lookahead token; otherwise raises a syntax
     * error.
     *
     * @param int|string token type or value
     */
    public function match($token)
    {
        if (is_string($token)) {
            $isMatch = ($this->lookahead['value'] === $token);
        } else {
            $isMatch = ($this->lookahead['type'] === $token);
        }

        if ($isMatch) {
            $this->_printer->println($this->lookahead['value']);
            $this->lookahead = $this->_scanner->next();
            $this->_errorDistance++;
        } else {
            $this->logError();
        }
    }

    public function logError($message = '')
    {
        if ($message === '') {
            if ($this->lookahead === null) {
                $message = 'Unexpected end of string.';
            } else {
                $message = 'Unexpected "' . $this->lookahead['value'] . '"';
            }
        }

        if ($this->_errorDistance >= self::MIN_ERROR_DISTANCE) {
            if ($this->lookahead !== null) {
                $message .= ' at position ' . $this->lookahead['position'];
            }
            $this->_errors[] = $message;
        }

        $this->_errorDistance = 0;
    }

    /**
     * Returns the scanner object associated with this object.
     *
     * @return Doctrine_Query_Scanner
     */
    public function getScanner()
    {
        return $this->_scanner;
    }

    /**
     * Returns the parse tree printer object associated with this object.
     *
     * @return Doctrine_Query_Printer
     */
    public function getPrinter()
    {
        return $this->_printer;
    }

    /**
     * Parses a query string.
     *
     * @throws Doctrine_Query_Parser_Exception if errors were detected in the query string
     */
    public function parse()
    {
        $this->lookahead = $this->_scanner->next();

        $this->getProduction('QueryLanguage')->execute();

        if ($this->lookahead !== null) {
            $this->logError('End of string expected');
        }

        if (count($this->_errors)) {
            $msg = 'Errors were detected during the query parsing ('
                 . implode('; ', $this->_errors) . ').';
            throw new Doctrine_Query_Parser_Exception($msg);
        }
    }
}