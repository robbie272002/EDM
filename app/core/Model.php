<?php
namespace app\core;

use PDO;

abstract class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function find($id) {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function all() {
        $query = "SELECT * FROM {$this->table}";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function create($data) {
        $fields = array_keys($data);
        $values = array_map(function($field) { return ":$field"; }, $fields);
        
        $query = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                 VALUES (" . implode(', ', $values) . ")";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);
        
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $fields = array_map(function($field) { return "$field = :$field"; }, array_keys($data));
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " 
                 WHERE {$this->primaryKey} = :id";
        
        $data['id'] = $id;
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
    
    public function where($conditions) {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "$field = :$field";
            $params[":$field"] = $value;
        }
        
        $query = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 